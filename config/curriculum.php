<?php

return [
    'stages' => [
        'fundamental' => [
            'label' => 'Ensino Fundamental',
            'formations' => [
                'formacao_geral_basica' => [
                    'label' => 'Formação Geral Básica',
                    'acronym' => 'FGB',
                    'legal_basis' => [
                        'law' => 'Lei nº 9.394/1996 (LDB)',
                        'normative_document' => 'Base Nacional Comum Curricular (BNCC)',
                    ],
                    'areas' => [
                        [
                            'name' => 'Linguagens',
                            'components' => [
                                'Língua Portuguesa',
                                'Arte',
                                'Educação Física',
                                'Língua Inglesa',
                            ],
                        ],
                        [
                            'name' => 'Matemática',
                            'components' => [
                                'Matemática',
                            ],
                        ],
                        [
                            'name' => 'Ciências da Natureza',
                            'components' => [
                                'Ciências',
                            ],
                        ],
                        [
                            'name' => 'Ciências Humanas',
                            'components' => [
                                'História',
                                'Geografia',
                            ],
                        ],
                        [
                            'name' => 'Ensino Religioso',
                            'components' => [
                                'Ensino Religioso',
                            ],
                        ],
                    ],
                ],
                'parte_complementar' => [
                    'label' => 'Parte Complementar',
                    'description' => 'Parte do currículo definida pelos sistemas de ensino e pelas unidades escolares, considerando as características locais, regionais, culturais e institucionais.',
                ],
            ],
        ],
        'medio' => [
            'label' => 'Ensino Médio',
            'formations' => [
                'formacao_geral_basica' => [
                    'label' => 'Formação Geral Básica',
                    'acronym' => 'FGB',
                    'legal_basis' => [
                        'law' => 'Lei nº 14.945/2024',
                        'normative_document' => 'Base Nacional Comum Curricular (BNCC)',
                    ],
                    'areas' => [
                        [
                            'name' => 'Linguagens e suas Tecnologias',
                            'components' => [
                                'Língua Portuguesa',
                                'Arte',
                                'Educação Física',
                                'Língua Inglesa',
                            ],
                        ],
                        [
                            'name' => 'Matemática e suas Tecnologias',
                            'components' => [
                                'Matemática',
                            ],
                        ],
                        [
                            'name' => 'Ciências da Natureza e suas Tecnologias',
                            'components' => [
                                'Biologia',
                                'Física',
                                'Química',
                            ],
                        ],
                        [
                            'name' => 'Ciências Humanas e Sociais Aplicadas',
                            'components' => [
                                'História',
                                'Geografia',
                                'Filosofia',
                                'Sociologia',
                            ],
                        ],
                    ],
                ],
                'itinerario_formativo' => [
                    'label' => 'Itinerário Formativo',
                    'description' => 'Percurso curricular escolhido pelo estudante, organizado conforme a oferta da rede de ensino e alinhado às áreas do conhecimento e à formação técnica e profissional.',
                ],
            ],
        ],
    ],
];
