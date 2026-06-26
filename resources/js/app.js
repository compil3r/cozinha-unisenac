/**
 * Cozinha Guiada — Interface fullscreen câmera
 *
 * Máquina de estados:
 *   idle → starting → camera → captured → analyzing → feedback
 *
 * Auto-TTS: lê a instrução automaticamente ao iniciar e ao avançar etapa.
 */

const CG = window.__CG__ || {};

// ─── Estado ───────────────────────────────────────────────────────────────────

const State = {
    phase:              'idle',   // idle | starting | camera | captured | analyzing | feedback | advance_ready
    currentStep:        CG.currentStep  ?? 0,
    totalSteps:         CG.totalSteps   ?? 0,
    steps:              CG.steps        ?? [],
    isMockMode:         CG.isMockMode   ?? false,
    mockScenario:       CG.mockScenario ?? 'complete',
    stream:             null,
    capturedBlob:       null,
    speechActive:       false,
    mockPanelOpen:      false,
    canAdvance:         false,
    canAdvanceManually: false,
    pendingAdvanceTo:   null,
    pendingCompleted:   false,
};

// ─── Atalhos DOM ──────────────────────────────────────────────────────────────

const el = (id) => document.getElementById(id);
const show = (id, display = 'flex') => { const e = el(id); if (e) e.style.display = display; };
const hide = (id) => { const e = el(id); if (e) e.style.display = 'none'; };
const text = (id, v) => { const e = el(id); if (e) e.textContent = v; };

function csrf() {
    return CG.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
}

async function postJson(url, data) {
    const r = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
        body: JSON.stringify(data),
    });
    return r.json();
}

async function postForm(url, form) {
    const r = await fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
        body: form,
    });
    return r.json();
}

// ─── Fluxo principal ──────────────────────────────────────────────────────────

async function start() {
    setPhase('starting');

    // Mostra overlays da câmera
    hide('screen-start');
    show('gradient-top', 'block');
    show('gradient-bottom', 'block');
    show('overlay-step', 'block');
    show('overlay-controls', 'flex');
    show('overlay-bottom', 'block');

    await openCamera();
    setPhase('camera');
    setRobot('falando');

    // Lê instrução automaticamente
    setTimeout(() => speakInstruction(), 600);
}

async function openCamera() {
    const video = el('camera-video');

    if (State.stream) {
        State.stream.getTracks().forEach(t => t.stop());
    }

    try {
        State.stream = await navigator.mediaDevices.getUserMedia({
            video: { facingMode: { ideal: 'environment' }, width: { ideal: 1280 }, height: { ideal: 960 } },
            audio: false,
        });
        video.srcObject = State.stream;
        show('camera-video', 'block');
        hide('camera-canvas');
        enableCaptureBtn();
    } catch (err) {
        console.error('Câmera:', err);
        showFeedbackError('Não foi possível acessar a câmera. Verifique as permissões do navegador.');
    }
}

// ─── Captura e verificação (botão shutter faz as duas coisas) ─────────────────

function captureOrVerify() {
    // Para TTS imediatamente se estiver tocando
    if ('speechSynthesis' in window) window.speechSynthesis.cancel();
    State.speechActive = false;
    const btnListen = el('btn-listen');
    if (btnListen) btnListen.textContent = '🔊';

    clearCardError();

    if (State.phase === 'camera')        return capturePhoto();
    if (State.phase === 'captured')      return analyzeStep();
    if (State.phase === 'feedback')      return retryCapture();
    if (State.phase === 'advance_ready') return confirmAdvance();
}

function capturePhoto() {
    const video  = el('camera-video');
    const canvas = el('camera-canvas');
    if (!State.stream || !video.srcObject) return;

    const MAX = CG.maxImageWidth ?? 1280;
    let w = video.videoWidth  || 1280;
    let h = video.videoHeight || 960;
    if (w > MAX) { h = Math.round(h * MAX / w); w = MAX; }

    canvas.width = w;
    canvas.height = h;
    canvas.getContext('2d').drawImage(video, 0, 0, w, h);

    canvas.toBlob((blob) => {
        if (!blob) return;
        State.capturedBlob = blob;

        playShutter();

        // Congela o frame
        show('camera-canvas', 'block');
        hide('camera-video');

        setPhase('captured');
        setShutterVerify();
        hideFeedback();
    }, 'image/jpeg', 0.82);
}

async function analyzeStep() {
    if (State.phase === 'analyzing' || !State.capturedBlob) return;
    setPhase('analyzing');

    showLoading('Analisando...');
    disableShutter();
    hideFeedback();

    const form = new FormData();
    form.append('image', State.capturedBlob, 'step.jpg');

    try {
        const data = await postForm(CG.routes.analyze, form);

        if (!data.success) {
            showFeedbackError(data.message || 'Erro ao analisar. Tente novamente.');
            return;
        }

        hideLoading();

        if (data.evaluation?.action === 'advance') {
            State.pendingAdvanceTo = data.advanced_to;
            State.pendingCompleted = !!data.completed;
        }

        showFeedback(data);

    } catch (err) {
        console.error(err);
        showFeedbackError('Erro de conexão. Tente novamente.');
    } finally {
        hideLoading();
    }
}

async function advanceManually() {
    try {
        const data = await postJson(CG.routes.advanceManually, {});
        if (!data.success) { showFeedbackError(data.message || 'Não foi possível avançar.'); return; }
        if (data.completed) { window.location.reload(); return; }
        hideFeedback();
        goToStep(data.current_step_index);
    } catch (err) {
        showFeedbackError('Erro de conexão.');
    }
}

function confirmAdvance() {
    setShutterAdvance();   // muda botão para "Avançando..." desabilitado
    if (State.pendingCompleted) {
        window.location.reload();
    } else {
        hideFeedback();
        goToStep(State.pendingAdvanceTo ?? (State.currentStep + 1));
    }
}

async function restart() {
    if (!confirm('Voltar à seleção de receitas?')) return;
    try { await postJson(CG.routes.restart, {}); } catch (_) {}
    window.location.reload();
}

function retryCapture() {
    setCardTheme('default');
    setRobot('falando');
    showInstructionView();
    clearCardError();
    resetToCamera();
}

function dismissFeedback() {
    showInstructionView();
    if (State.phase === 'feedback') setPhase('captured');
}

// ─── Navegação entre etapas ───────────────────────────────────────────────────

function goToStep(index) {
    State.currentStep = index;
    const step = State.steps[index];
    if (!step) return;

    updateStepUI(step, index);
    clearCardError();
    setRobot('falando');
    resetToCamera();
    setTimeout(() => speakInstruction(), 500);  // lê instrução automaticamente
}

function updateStepUI(step, index) {
    // Contador
    text('step-counter', `Etapa ${index + 1} de ${State.totalSteps}`);
    text('step-title-overlay', step.title);
    text('instruction-text', step.instruction);

    // Pontos de progresso
    document.querySelectorAll('#progress-dots .dot').forEach((dot, i) => {
        const active = i === index;
        dot.style.background = active ? '#fff' : 'rgba(255,255,255,.3)';
        dot.style.transform  = active ? 'scale(1.3)' : 'scale(1)';
    });

    // Checklist
    const cl = el('checklist-items');
    if (cl) {
        cl.innerHTML = (step.expected_items || []).map(item =>
            `<span style="background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.15);
                          border-radius:20px;padding:3px 10px;color:rgba(255,255,255,.75);
                          font-size:12px;white-space:nowrap;">${esc(item)}</span>`
        ).join('');
    }

    // Avanço manual
    const ma = el('manual-advance-area');
    if (ma) ma.style.visibility = step.allow_manual_advance ? 'visible' : 'hidden';
}

function resetToCamera() {
    State.capturedBlob = null;

    const video  = el('camera-video');
    const canvas = el('camera-canvas');

    if (State.stream && video) {
        show('camera-video', 'block');
        hide('camera-canvas');
    }

    setCardTheme('default');
    setPhase('camera');
    setShutterCapture();
    enableCaptureBtn();
}

// ─── Tema do card ──────────────────────────────────────────────────────────────

function setCardTheme(theme) {
    const card = el('bottom-card');
    if (!card) return;
    const t = {
        default:   { bg: 'rgba(10,10,8,.72)',   border: 'rgba(255,255,255,.1)' },
        retry:     { bg: 'rgba(65,8,8,.85)',     border: 'rgba(200,45,45,.35)' },
        uncertain: { bg: 'rgba(8,14,50,.85)',    border: 'rgba(50,80,200,.3)'  },
        advance:   { bg: 'rgba(6,38,4,.85)',     border: 'rgba(60,150,30,.3)'  },
    }[theme] || { bg: 'rgba(10,10,8,.72)', border: 'rgba(255,255,255,.1)' };
    card.style.background   = t.bg;
    card.style.borderColor  = t.border;
}

// ─── Botão de captura (colorido, estilo cozinha) ──────────────────────────────

function applyBtnStyle(btn, text, bg, shadow, color, disabled) {
    btn.textContent      = text;
    btn.style.background = bg;
    btn.style.boxShadow  = shadow;
    btn.style.color      = color;
    btn.disabled         = disabled;
    btn.style.opacity    = '1';
    btn.style.cursor     = disabled ? 'not-allowed' : 'pointer';
}

function setShutterCapture() {
    const btn = el('btn-capture');
    if (!btn) return;
    applyBtnStyle(btn,
        '📸 Pronto!',
        'linear-gradient(135deg,#b85c08 0%,#e8941a 100%)',
        '0 6px 28px rgba(184,92,8,.55)',
        '#fff', false);
    btn.title = 'Tirar foto';
}

function setShutterVerify() {
    const btn = el('btn-capture');
    if (!btn) return;
    applyBtnStyle(btn,
        '🔍 Conferir!',
        'linear-gradient(135deg,#3a6e18 0%,#6aaa30 100%)',
        '0 6px 28px rgba(58,110,24,.6)',
        '#e8ffd4', false);
    btn.title = 'Verificar etapa';
}

function setShutterRetry(action) {
    const btn = el('btn-capture');
    if (!btn) return;
    if (action === 'ask_manual_confirmation') {
        applyBtnStyle(btn,
            '📷 Tentar novamente',
            'linear-gradient(135deg,#0e1a50 0%,#2a4ab0 100%)',
            '0 6px 28px rgba(20,60,200,.45)',
            '#c8dcff', false);
    } else {
        applyBtnStyle(btn,
            '📷 Tentar novamente',
            'linear-gradient(135deg,#5e0808 0%,#b02828 100%)',
            '0 6px 28px rgba(160,24,24,.55)',
            '#ffd8d8', false);
    }
    btn.title = 'Tentar novamente';
}

function setShutterAdvanceReady(isLast = false) {
    const btn = el('btn-capture');
    if (!btn) return;
    const label = isLast ? '🎉 Ver resultado!' : '➡️ Próxima etapa!';
    applyBtnStyle(btn,
        label,
        'linear-gradient(135deg,#1e5210 0%,#4aaa20 100%)',
        '0 6px 28px rgba(50,160,20,.5)',
        '#e8ffd4', false);   // habilitado — aguarda clique do usuário
    btn.title = label;
}

function setShutterAdvance() {
    const btn = el('btn-capture');
    if (!btn) return;
    applyBtnStyle(btn,
        '✅ Avançando!',
        'linear-gradient(135deg,#1e5210 0%,#4aaa20 100%)',
        '0 6px 28px rgba(50,160,20,.5)',
        '#e8ffd4', true);   // desabilitado durante a transição
}

function enableCaptureBtn() {
    const btn = el('btn-capture');
    if (!btn) return;
    btn.disabled      = false;
    btn.style.opacity = '1';
    btn.style.cursor  = 'pointer';
}

function disableShutter() {
    const btn = el('btn-capture');
    if (!btn) return;
    applyBtnStyle(btn,
        '⏳ Analisando...',
        'rgba(255,255,255,.07)',
        'none',
        'rgba(255,255,255,.3)', true);
}

// ─── Fases ────────────────────────────────────────────────────────────────────

function setPhase(phase) {
    State.phase = phase;
}

// ─── Views do card (instrução ↔ feedback) ─────────────────────────────────────

function showInstructionView() {
    const vi = el('view-instruction');
    const vf = el('view-feedback');
    if (vi) { vi.style.opacity = '0'; vi.style.display = 'block';
        requestAnimationFrame(() => { vi.style.transition = 'opacity .2s'; vi.style.opacity = '1'; }); }
    if (vf) vf.style.display = 'none';

    // Restaura visibilidade do "Avançar mesmo assim" com base na etapa atual
    const step = State.steps[State.currentStep];
    const ma   = el('manual-advance-area');
    if (ma && step) ma.style.visibility = step.allow_manual_advance ? 'visible' : 'hidden';
}

function showFeedbackView() {
    const vi = el('view-instruction');
    const vf = el('view-feedback');
    if (vi) vi.style.display = 'none';
    if (vf) { vf.style.opacity = '0'; vf.style.display = 'block';
        requestAnimationFrame(() => { vf.style.transition = 'opacity .25s'; vf.style.opacity = '1'; }); }
}

// ─── Feedback ─────────────────────────────────────────────────────────────────

function showFeedback(data) {
    const analysis   = data.analysis   || {};
    const evaluation = data.evaluation || {};
    const action     = evaluation.action || 'retry';
    const poorImg    = analysis.image_quality === 'poor';

    // Mensagem de feedback
    const fbText = el('fb-text');
    if (fbText) fbText.textContent = analysis.feedback || '';

    // Barra de confiança
    const conf    = Math.round((analysis.confidence || 0) * 100);
    const confBar = el('fb-conf-bar');
    const confVal = el('fb-conf-val');
    if (confBar) {
        confBar.style.width      = conf + '%';
        confBar.style.background = conf >= 75 ? '#6aaa30' : conf >= 50 ? '#e0a050' : '#e05252';
    }
    if (confVal) confVal.textContent = conf + '%';

    // Itens detectados
    const det     = el('fb-detected');
    const detList = el('fb-detected-list');
    const items   = analysis.detected_items || [];
    if (det && detList) {
        det.style.display = items.length ? 'block' : 'none';
        if (items.length) {
            detList.innerHTML = items.map(i =>
                `<span style="background:rgba(255,255,255,.12);border-radius:20px;padding:2px 8px;
                              color:rgba(255,255,255,.7);font-size:11px;">${esc(i)}</span>`
            ).join('');
        }
    }


    // Cor do card + estado do botão + robozinha + som
    if (action === 'advance') {
        setCardTheme('advance');
        setShutterAdvanceReady(State.pendingCompleted);
        setRobot('deliciando');
        playSuccess();
        setPhase('advance_ready');
    } else if (action === 'ask_manual_confirmation') {
        setCardTheme('uncertain');
        setShutterRetry('ask_manual_confirmation');
        setRobot('falando');
        setPhase('feedback');
    } else {
        setCardTheme('retry');
        setShutterRetry('retry');
        setRobot('triste');
        setPhase('feedback');
    }

    // Visibilidade do "Avançar mesmo assim" (única localização — secondary actions)
    const ma = el('manual-advance-area');
    if (ma) {
        const showManual = evaluation.can_advance_manually && action !== 'advance';
        ma.style.visibility = showManual ? 'visible' : 'hidden';
    }

    // Lê o feedback em voz alta
    speak(analysis.feedback || '');

    // Exibe a vista de feedback dentro do card
    showFeedbackView();
}

function showFeedbackError(msg) {
    hideLoading();
    setRobot('triste');

    // Erro aparece no view de instrução (não troca para view de feedback)
    showInstructionView();

    const errEl   = el('card-error');
    const errText = el('card-error-text');
    if (errEl && errText) {
        errText.textContent  = msg;
        errEl.style.display  = 'block';
        errEl.style.opacity  = '0';
        requestAnimationFrame(() => {
            errEl.style.transition = 'opacity .25s ease';
            errEl.style.opacity    = '1';
        });
    }

    setPhase('camera');
    setShutterCapture();
    enableCaptureBtn();
}

function clearCardError() {
    const errEl = el('card-error');
    if (!errEl || errEl.style.display === 'none') return;
    errEl.style.transition = 'opacity .2s ease';
    errEl.style.opacity    = '0';
    setTimeout(() => { errEl.style.display = 'none'; }, 200);
}

function hideFeedback() {
    showInstructionView();
}

// ─── Loading ──────────────────────────────────────────────────────────────────

function showLoading(msg = 'Processando...') {
    const ov = el('overlay-loading');
    if (ov) {
        ov.style.display = 'flex';
        text('loading-msg', msg);
    }
}

function hideLoading() {
    hide('overlay-loading');
}

// ─── Text-to-Speech ───────────────────────────────────────────────────────────

function speak(text) {
    if (!('speechSynthesis' in window) || !text) return;
    window.speechSynthesis.cancel();
    State.speechActive = false;

    const utt = new SpeechSynthesisUtterance(text);
    utt.lang  = 'pt-BR';
    utt.rate  = 0.95;
    utt.pitch = 1;

    const voices = window.speechSynthesis.getVoices();
    const ptVoice = voices.find(v => v.lang.startsWith('pt'));
    if (ptVoice) utt.voice = ptVoice;

    State.speechActive = true;
    const btn = el('btn-listen');
    if (btn) btn.textContent = '🔇';

    utt.onend = utt.onerror = () => {
        State.speechActive = false;
        if (btn) btn.textContent = '🔊';
    };

    window.speechSynthesis.speak(utt);
}

function speakInstruction() {
    const step = State.steps[State.currentStep];
    if (!step) return;
    speak(`${step.title}. ${step.instruction}`);
}

// ─── Sons (Web Audio API) ─────────────────────────────────────────────────────

let _audioCtx = null;
function audioCtx() {
    if (!_audioCtx) _audioCtx = new (window.AudioContext || window.webkitAudioContext)();
    return _audioCtx;
}

function playShutter() {
    // Usa arquivo customizado se existir em public/sounds/shutter.mp3
    const audio = new Audio('/sounds/shutter.mp3');
    audio.play().catch(() => {
        // Fallback: som gerado via Web Audio
        try {
            const ctx  = audioCtx();
            const dur  = 0.07;
            const buf  = ctx.createBuffer(1, Math.floor(ctx.sampleRate * dur), ctx.sampleRate);
            const data = buf.getChannelData(0);
            for (let i = 0; i < data.length; i++) {
                data[i] = (Math.random() * 2 - 1) * Math.pow(1 - i / data.length, 6) * 0.6;
            }
            const src  = ctx.createBufferSource();
            const gain = ctx.createGain();
            src.buffer = buf;
            gain.gain.setValueAtTime(1, ctx.currentTime);
            gain.gain.linearRampToValueAtTime(0, ctx.currentTime + dur);
            src.connect(gain);
            gain.connect(ctx.destination);
            src.start();
        } catch (_) {}
    });
}

function playSuccess() {
    const audio = new Audio('/sounds/success.mp3');
    audio.play().catch(() => {
        try {
            const ctx   = audioCtx();
            const notes = [523.25, 659.25, 783.99, 1046.50]; // C5 E5 G5 C6
            notes.forEach((freq, i) => {
                const osc  = ctx.createOscillator();
                const gain = ctx.createGain();
                const t    = ctx.currentTime + i * 0.13;
                osc.type             = 'sine';
                osc.frequency.value  = freq;
                gain.gain.setValueAtTime(0, t);
                gain.gain.linearRampToValueAtTime(0.28, t + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.001, t + 0.38);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start(t);
                osc.stop(t + 0.4);
            });
        } catch (_) {}
    });
}

// ─── Robozinha ────────────────────────────────────────────────────────────────

function setRobot(state) {
    const img = el('robo-char');
    if (!img || !CG.roboImages) return;
    const src = CG.roboImages[state] || CG.roboImages.falando;
    if (img.src.endsWith(src.split('/').pop()) && img.style.opacity !== '0') return;
    img.style.opacity = '0';
    setTimeout(() => { img.src = src; img.style.opacity = '1'; }, 180);
}

// ─── Modo mock ────────────────────────────────────────────────────────────────

function toggleMockPanel() {
    State.mockPanelOpen = !State.mockPanelOpen;
    const panel = el('mock-panel');
    if (panel) panel.style.display = State.mockPanelOpen ? 'block' : 'none';
}

async function setMockScenario(scenario) {
    try {
        await postJson(CG.routes.mockScenario, { scenario });
        State.mockScenario = scenario;
        document.querySelectorAll('.mock-btn').forEach(btn => {
            const active = btn.dataset.scenario === scenario;
            btn.style.boxShadow = active ? `0 0 0 2px ${btn.style.color}` : 'none';
        });
        toggleMockPanel();
    } catch (err) {
        console.error('Erro ao definir cenário:', err);
    }
}

// ─── Painel de debug ──────────────────────────────────────────────────────────

let debugPanelOpen = false;

function toggleDebug() {
    debugPanelOpen = !debugPanelOpen;
    const panel = el('debug-panel');
    if (panel) panel.style.display = debugPanelOpen ? 'block' : 'none';
    if (debugPanelOpen) refreshDebug();
}

async function refreshDebug() {
    if (!CG.routes?.debug) return;
    const pre = el('debug-content');
    if (!pre) return;
    pre.textContent = 'Carregando...';
    try {
        const r    = await fetch(CG.routes.debug, { headers: { Accept: 'application/json' } });
        const data = await r.json();
        pre.textContent = JSON.stringify(data, null, 2);
    } catch (e) {
        pre.textContent = 'Erro ao carregar: ' + e.message;
    }
}

// ─── Utilitários ──────────────────────────────────────────────────────────────

function esc(str) {
    return String(str)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;');
}

// Carrega vozes quando disponíveis (alguns browsers carregam async)
if ('speechSynthesis' in window) {
    window.speechSynthesis.getVoices();
    window.speechSynthesis.onvoiceschanged = () => window.speechSynthesis.getVoices();
}

// ─── API pública ──────────────────────────────────────────────────────────────

window.CozinhaGuiada = {
    start,
    captureOrVerify,
    analyzeStep,
    advanceManually,
    restart,
    retryCapture,
    dismissFeedback,
    speakInstruction,
    toggleMockPanel,
    setMockScenario,
    clearCardError,
    toggleDebug,
    refreshDebug,
};
