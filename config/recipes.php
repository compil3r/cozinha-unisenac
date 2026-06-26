<?php

/*
|--------------------------------------------------------------------------
| Receitas da Cozinha Guiada
|--------------------------------------------------------------------------
|
| 'image' aponta para public/images/recipes/{arquivo}.png
| Se o arquivo não existir, o frontend usa o 'emoji' como fallback.
| RECIPE_DEBUG=true ativa a receita de teste de gestos.
|
*/

$recipes = [

    // ── Receita 1: Iogurte com frutas e granola ─────────────────────────────
    'iogurte-frutas-granola' => [
        'id'            => 'iogurte-frutas-granola',
        'title'         => 'Iogurte com frutas e granola',
        'description'   => 'Sobremesa fria, colorida e nutritiva. Aprenda a montar camadas com equilíbrio visual.',
        'emoji'         => '🫙',
        'image'         => 'images/recipes/iogurte-frutas-granola.png',
        'difficulty'    => 'fácil',
        'time'          => '10 min',
        'servings'      => '1 porção',
        'cold'          => true,
        'requires_fire' => false,

        'ingredients' => [
            'Iogurte natural ou grego (1 pote)',
            'Frutas a gosto (morango, banana, manga, kiwi)',
            'Granola (3–4 colheres)',
            'Mel (opcional)',
        ],
        'utensils' => [
            'Taça, bowl ou qualquer recipiente',
            'Colher de sobremesa',
            'Faca pequena e tábua',
        ],

        'steps' => [
            [
                'index'       => 0,
                'title'       => 'Organização da bancada',
                'instruction' => 'Separe sobre a bancada: um recipiente (taça, bowl ou pote), o iogurte, as frutas, a granola e uma colher.',
                'tip'         => 'Cada um monta do seu jeito — qualquer recipiente serve.',
                'expected_items'  => ['Algum recipiente (taça, bowl, pote ou copo)','Iogurte (pote ou já servido)','Frutas (inteiras, picadas ou num recipiente)','Granola (embalagem, pote ou tigela)','Colher'],
                'visual_criteria' => [
                    'Algum recipiente visível onde o iogurte será montado',
                    'Iogurte presente de alguma forma (pote fechado, aberto ou já servido)',
                    'Pelo menos uma fruta ou pedaço de fruta identificável',
                    'Granola presente de alguma forma (embalagem, tigela ou granéis)',
                ],
                'allow_manual_advance'  => true,
                'manual_advance_reason' => 'Se tiver tudo separado mas difícil de ver na imagem, avance.',
            ],
            [
                'index'       => 1,
                'title'       => 'Primeira camada de iogurte',
                'instruction' => 'Coloque iogurte no recipiente. Qualquer quantidade está ótimo.',
                'tip'         => 'Não precisa ser perfeito — o importante é ter iogurte no fundo.',
                'expected_items'  => ['Recipiente com iogurte'],
                'visual_criteria' => [
                    'Recipiente com alguma substância branca ou creme dentro',
                    'Parece ser iogurte ou similar',
                ],
                'allow_manual_advance'  => true,
                'manual_advance_reason' => 'Avance quando o iogurte estiver no recipiente.',
            ],
            [
                'index'       => 2,
                'title'       => 'Camada de frutas',
                'instruction' => 'Adicione frutas por cima do iogurte — inteiras, picadas, como preferir.',
                'tip'         => 'Não precisa cobrir tudo, uma camada rápida já conta.',
                'expected_items'  => ['Frutas no recipiente (de qualquer forma)'],
                'visual_criteria' => [
                    'Algum pedaço ou fruta inteira visível no recipiente ou por cima do iogurte',
                    'Parece haver fruta adicionada',
                ],
                'allow_manual_advance'  => true,
                'manual_advance_reason' => 'Avance quando as frutas estiverem adicionadas.',
            ],
            [
                'index'       => 3,
                'title'       => 'Camada de granola',
                'instruction' => 'Coloque um pouco de granola por cima. Quanto quiser.',
                'tip'         => 'Pode ser pouca ou muita — o importante é estar lá.',
                'expected_items'  => ['Granola no recipiente'],
                'visual_criteria' => [
                    'Algo com textura granulada, farofenta ou crocante visível no recipiente',
                    'Parece ser granola ou cereal por cima das frutas ou iogurte',
                ],
                'allow_manual_advance'  => true,
                'manual_advance_reason' => 'Avance quando a granola estiver adicionada.',
            ],
            [
                'index'       => 4,
                'title'       => 'Apresentação final',
                'instruction' => 'Mostre o resultado para a câmera. Ficou bonito? Adicione mel se quiser!',
                'tip'         => 'Boa iluminação ajuda. Pode segurar o recipiente ou deixar na bancada.',
                'expected_items'  => ['Recipiente montado com iogurte e pelo menos um ingrediente adicional'],
                'visual_criteria' => [
                    'Recipiente com iogurte visível',
                    'Pelo menos frutas ou granola identificáveis',
                    'Parece um prato montado e finalizado',
                ],
                'allow_manual_advance'  => true,
                'manual_advance_reason' => 'Se ficou pronto do seu jeito, avance.',
            ],
        ],
    ],

    // ── Receita 2: Sanduíche de queijo e presunto ────────────────────────────
    'sanduiche-queijo-presunto' => [
        'id'            => 'sanduiche-queijo-presunto',
        'title'         => 'Sanduíche de queijo e presunto',
        'description'   => 'Clássico e rápido. Monte as camadas na ordem certa e deixe a câmera avaliar.',
        'emoji'         => '🥪',
        'image'         => 'images/recipes/sanduiche-queijo-presunto.png',
        'difficulty'    => 'fácil',
        'time'          => '5 min',
        'servings'      => '1 sanduíche',
        'cold'          => true,
        'requires_fire' => false,

        'ingredients' => [
            'Pão de forma (2 fatias)',
            'Fatias de presunto (2–3)',
            'Fatia de queijo (1–2)',
            'Maionese ou mostarda a gosto',
            'Folhas de alface',
            'Rodelas de tomate',
        ],
        'utensils' => [
            'Tábua de corte',
            'Faca de mesa',
            'Prato',
        ],

        'steps' => [
            [
                'index'       => 0,
                'title'       => 'Separar os ingredientes',
                'instruction' => 'Coloque sobre a tábua: as duas fatias de pão, o presunto, o queijo, a alface, o tomate e o condimento. Organize tudo antes de montar.',
                'tip'         => 'Deixar tudo à mão evita que o pão resseque enquanto você procura os ingredientes.',
                'expected_items'  => ['Pão de forma','Presunto','Queijo','Alface','Tomate','Tábua'],
                'visual_criteria' => [
                    'Tábua visível com ingredientes separados',
                    'Pão de forma identificável',
                    'Frios (queijo ou presunto) identificáveis',
                    'Ao menos uma folha de alface ou rodela de tomate visível',
                ],
                'allow_manual_advance'  => false,
                'manual_advance_reason' => null,
            ],
            [
                'index'       => 1,
                'title'       => 'Preparar a base',
                'instruction' => 'Coloque uma fatia de pão na tábua e espalhe maionese (ou mostarda) por toda a superfície.',
                'tip'         => 'Uma camada fina e uniforme de condimento mantém o sanduíche úmido sem encharcar o pão.',
                'expected_items'  => ['Fatia de pão com condimento'],
                'visual_criteria' => [
                    'Fatia de pão visível na tábua',
                    'Substância clara ou amarelada espalhada sobre o pão',
                ],
                'allow_manual_advance'  => true,
                'manual_advance_reason' => 'A quantidade de maionese é pessoal. Avance quando a base estiver pronta.',
            ],
            [
                'index'       => 2,
                'title'       => 'Montar o recheio',
                'instruction' => 'Adicione sobre o pão: queijo, presunto, rodelas de tomate e folhas de alface, nessa ordem.',
                'tip'         => 'Coloque o queijo logo acima do pão para "segurar" os outros ingredientes.',
                'expected_items'  => ['Queijo sobre o pão','Presunto','Rodela de tomate','Alface'],
                'visual_criteria' => [
                    'Queijo visível sobre o pão',
                    'Presunto identificável no recheio',
                    'Tomate ou alface visível',
                    'Montagem em camadas',
                ],
                'allow_manual_advance'  => true,
                'manual_advance_reason' => 'A ordem das camadas não é visualmente determinante. Avance quando o recheio estiver completo.',
            ],
            [
                'index'       => 3,
                'title'       => 'Fechar e apresentar',
                'instruction' => 'Coloque a segunda fatia de pão por cima e mostre o sanduíche finalizado para a câmera.',
                'tip'         => 'Para uma foto melhor, corte o sanduíche na diagonal e mostre o interior.',
                'expected_items'  => ['Sanduíche fechado com duas fatias de pão'],
                'visual_criteria' => [
                    'Duas fatias de pão visíveis (sanduíche fechado)',
                    'Recheio visível nas bordas ou em corte',
                    'Aparência de sanduíche finalizado',
                ],
                'allow_manual_advance'  => false,
                'manual_advance_reason' => null,
            ],
        ],
    ],

];

// ── Receita 3: Debug de gestos de mão (só com RECIPE_DEBUG=true) ─────────────
if ((bool) env('RECIPE_DEBUG', false)) {
    $recipes['debug-maos'] = [
        'id'            => 'debug-maos',
        'title'         => 'Teste de gestos de mão',
        'description'   => 'Receita de depuração. Mostra gestos simples para validar se o sistema de visão está funcionando.',
        'emoji'         => '🖐️',
        'image'         => 'images/recipes/debug-maos.png',
        'difficulty'    => 'debug',
        'time'          => '1 min',
        'servings'      => 'sistema',
        'cold'          => false,
        'requires_fire' => false,

        'ingredients' => ['Sua mão'],
        'utensils'    => ['Câmera'],

        'steps' => [
            [
                'index'       => 0,
                'title'       => 'Mão aberta',
                'instruction' => 'Mostre a palma da sua mão aberta para a câmera, com os cinco dedos esticados e bem separados.',
                'tip'         => 'Deixe a mão bem iluminada e centralizada no quadro.',
                'expected_items'  => [
                    'Palma da mão humana voltada para a câmera',
                    'Exatamente cinco dedos visíveis e esticados',
                    'Dedos claramente separados entre si',
                ],
                'visual_criteria' => [
                    'Mão humana claramente visível',
                    'TODOS os cinco dedos estão esticados e separados',
                    'A palma (frente da mão) está voltada para a câmera',
                    'Nenhum dedo está dobrado ou fechado',
                ],
                'reject_if' => [
                    'Algum dedo está dobrado ou fechado',
                    'Menos de cinco dedos visíveis ou esticados',
                    'A mão está em forma de punho',
                    'Apenas um ou poucos dedos estão levantados',
                ],
                'allow_manual_advance'  => true,
                'manual_advance_reason' => 'Use para testar avanço manual.',
            ],
            [
                'index'       => 1,
                'title'       => 'Dedo indicador levantado',
                'instruction' => 'Feche a mão e levante apenas o dedo indicador apontando para cima.',
                'tip'         => 'Os outros dedos devem estar claramente dobrados.',
                'expected_items'  => [
                    'Exatamente um dedo (o indicador) apontando para cima',
                    'Os outros quatro dedos claramente dobrados ou fechados sobre a palma',
                ],
                'visual_criteria' => [
                    'Mão humana visível',
                    'APENAS UM dedo está levantado/esticado',
                    'Os outros quatro dedos estão claramente fechados ou dobrados',
                    'O dedo levantado aponta para cima',
                ],
                'reject_if' => [
                    'Mais de um dedo está levantado ou esticado',
                    'A mão está aberta com múltiplos dedos estendidos',
                    'A mão está em punho sem nenhum dedo levantado',
                    'Dois ou mais dedos estão apontando para cima simultaneamente',
                ],
                'allow_manual_advance'  => true,
                'manual_advance_reason' => 'Use para testar avanço manual.',
            ],
            [
                'index'       => 2,
                'title'       => 'Mão em punho',
                'instruction' => 'Feche completamente a mão formando um punho. Mostre para a câmera.',
                'tip'         => 'O dorso ou a frente do punho — qualquer um funciona.',
                'expected_items'  => [
                    'Mão fechada em forma de punho compacto',
                    'Nenhum dedo esticado ou levantado',
                ],
                'visual_criteria' => [
                    'Mão humana visível',
                    'TODOS os dedos estão claramente dobrados e fechados',
                    'Forma de punho compacto identificável',
                    'Nenhum dedo esticado, aberto ou levantado',
                ],
                'reject_if' => [
                    'Qualquer dedo está esticado ou levantado',
                    'A mão está aberta ou semi-aberta',
                    'Um ou mais dedos apontam para cima',
                    'A palma aberta está voltada para a câmera',
                ],
                'allow_manual_advance'  => true,
                'manual_advance_reason' => 'Use para testar avanço manual.',
            ],
        ],
    ];
}

return [
    'available' => $recipes,
    'default'   => 'iogurte-frutas-granola',
];
