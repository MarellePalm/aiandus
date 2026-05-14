<?php

return [

    'required' => ':attribute on kohustuslik.',
    'email' => ':attribute peab olema korrektne.',
    'exists' => 'Valitud :attribute ei sobi.',

    'min' => [
        'string' => ':attribute peab olema vähemalt :min tähemärki.',
        'numeric' => ':attribute peab olema vähemalt :min.',
        'file' => ':attribute peab olema vähemalt :min kilobaiti.',
    ],

    'max' => [
        'string' => ':attribute võib olla kuni :max tähemärki.',
        'numeric' => ':attribute võib olla kuni :max.',
        'file' => ':attribute võib olla maksimaalselt :max kilobaiti.',
    ],
    'between' => [
        'numeric' => ':attribute peab olema vahemikus :min kuni :max.',
        'string' => ':attribute peab olema vahemikus :min kuni :max tähemärki.',
        'file' => ':attribute peab olema vahemikus :min kuni :max kilobaiti.',
        'array' => ':attribute peab sisaldama vahemikus :min kuni :max elementi.',
    ],

    'confirmed' => ':attribute ei ühti kinnitusega.',
    'unique' => ':attribute on juba kasutusel.',
    'string' => ':attribute peab olema tekst.',
    'integer' => ':attribute peab olema täisarv.',
    'date' => ':attribute peab olema korrektne kuupäev.',
    'array' => ':attribute peab olema massiiv.',
    'file' => ':attribute peab olema fail.',
    'image' => ':attribute peab olema pildifail (jpg, jpeg, png, bmp, gif, svg või webp).',
    'mimes' => ':attribute peab olema tüüpi: :values.',
    'dimensions' => ':attribute mõõdud ei sobi. Pildi laius ja kõrgus ei tohi ületada :max_width×:max_height px.',

    'custom' => [
        'image' => [
            'max' => 'Pilt võib olla maksimaalselt 5 MB.',
        ],
        'photos.*' => [
            'max' => 'Foto võib olla maksimaalselt 5 MB.',
        ],
    ],

    'attributes' => [
        'email' => 'E-posti aadress',
        'password' => 'Parool',
        'password_confirmation' => 'Parooli kinnitus',
        'name' => 'Nimi',
        'subtitle' => 'Sort',
        'planted_at' => 'Istutamise kuupäev',
        'category_id' => 'Kategooria',
        'amount_text' => 'Kogus',
        'year' => 'Aasta',
        'expires_at' => 'Aegumise kuupäev',
        'phone' => 'Telefon',
        'address' => 'Aadress',
        'image' => 'Pilt',
        'photos' => 'Fotod',
        'photos.*' => 'Foto',
        'quantity' => 'Taimede arv',
    ],

];
