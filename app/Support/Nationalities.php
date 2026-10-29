<?php

namespace App\Support;

use Illuminate\Support\Str;

class Nationalities
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [
            'Alemã' => 'Alemanha - Alemã', 'Angolana' => 'Angola - Angolana',
            'Argentina' => 'Argentina - Argentina', 'Australiana' => 'Austrália - Australiana',
            'Austríaca' => 'Áustria - Austríaca', 'Belga' => 'Bélgica - Belga',
            'Boliviana' => 'Bolívia - Boliviana', 'Brasileira' => 'Brasil - Brasileira',
            'Britânica' => 'Reino Unido - Britânica', 'Cabo-verdiana' => 'Cabo Verde - Cabo-verdiana',
            'Canadense' => 'Canadá - Canadense', 'Chilena' => 'Chile - Chilena',
            'Chinesa' => 'China - Chinesa', 'Colombiana' => 'Colômbia - Colombiana',
            'Congolesa' => 'República Democrática do Congo - Congolesa',
            'Coreana' => 'Coreia do Sul - Coreana', 'Costarriquenha' => 'Costa Rica - Costarriquenha',
            'Cubana' => 'Cuba - Cubana', 'Dinamarquesa' => 'Dinamarca - Dinamarquesa',
            'Dominicana' => 'República Dominicana - Dominicana',
            'Egípcia' => 'Egito - Egípcia', 'Equatoriana' => 'Equador - Equatoriana',
            'Espanhola' => 'Espanha - Espanhola', 'Estadunidense' => 'Estados Unidos - Estadunidense',
            'Filipina' => 'Filipinas - Filipina', 'Finlandesa' => 'Finlândia - Finlandesa',
            'Francesa' => 'França - Francesa', 'Grega' => 'Grécia - Grega',
            'Guatemalteca' => 'Guatemala - Guatemalteca', 'Guineense' => 'Guiné-Bissau - Guineense',
            'Haitiana' => 'Haiti - Haitiana', 'Hondurenha' => 'Honduras - Hondurenha',
            'Indiana' => 'Índia - Indiana', 'Indonésia' => 'Indonésia - Indonésia',
            'Irlandesa' => 'Irlanda - Irlandesa', 'Israelense' => 'Israel - Israelense',
            'Italiana' => 'Itália - Italiana', 'Japonesa' => 'Japão - Japonesa',
            'Libanesa' => 'Líbano - Libanesa', 'Marroquina' => 'Marrocos - Marroquina',
            'Mexicana' => 'México - Mexicana', 'Moçambicana' => 'Moçambique - Moçambicana',
            'Neerlandesa' => 'Países Baixos - Neerlandesa',
            'Nigeriana' => 'Nigéria - Nigeriana', 'Norueguesa' => 'Noruega - Norueguesa',
            'Paraguaia' => 'Paraguai - Paraguaia', 'Peruana' => 'Peru - Peruana',
            'Polonesa' => 'Polônia - Polonesa', 'Portuguesa' => 'Portugal - Portuguesa',
            'Romena' => 'Romênia - Romena', 'Russa' => 'Rússia - Russa',
            'Senegalesa' => 'Senegal - Senegalesa', 'Síria' => 'Síria - Síria',
            'Sul-africana' => 'África do Sul - Sul-africana', 'Sueca' => 'Suécia - Sueca',
            'Suíça' => 'Suíça - Suíça', 'Tailandesa' => 'Tailândia - Tailandesa',
            'Timorense' => 'Timor-Leste - Timorense', 'Turca' => 'Turquia - Turca',
            'Ucraniana' => 'Ucrânia - Ucraniana', 'Uruguaia' => 'Uruguai - Uruguaia',
            'Venezuelana' => 'Venezuela - Venezuelana', 'Vietnamita' => 'Vietnã - Vietnamita',
        ];

        uasort($options, fn (string $a, string $b): int => strcmp(Str::ascii($a), Str::ascii($b)));

        return ['Brasileira' => $options['Brasileira']] + $options;
    }
}
