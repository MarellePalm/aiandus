<?php

return [

    'required' => ':attribute on kohustuslik.',
    'email' => ':attribute peab olema korrektne.',

    'min' => [
        'string' => ':attribute peab olema vähemalt :min tähemärki.',
        'numeric' => ':attribute peab olema vähemalt :min.',
    ],

    'max' => [
        'string' => ':attribute võib olla kuni :max tähemärki.',
        'numeric' => ':attribute võib olla kuni :max.',
    ],

    'confirmed' => ':attribute ei ühti kinnitusega.',
    'unique' => ':attribute on juba kasutusel.',
    'string' => ':attribute peab olema tekst.',
    'integer' => ':attribute peab olema täisarv.',

    'attributes' => [
        'email' => 'E-posti aadress',
        'password' => 'Parool',
        'password_confirmation' => 'Parooli kinnitus',
        'name' => 'Nimi',
        'phone' => 'Telefon',
        'address' => 'Aadress',
    ],

];
