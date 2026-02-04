<?php

return [
    // разрыв OUT->IN больше этого => новая смена
    'shift_break_hours' => 3,

    // смены короче этого считаем шумом
    'min_shift_minutes' => 30,

    // защита от “залипаний”
    'max_shift_hours'   => 12,

    // защита от мусорных пар IN->OUT
    'max_segment_hours' => 12,

    // если подряд два "Вход" без "Выход": при разрыве >= N минут сбрасываем вход на более поздний
    'dup_in_reset_minutes' => 15,
    
    'long_gap_minutes' => 60,
    'lunch_gap_minutes' => 30,
    'lunch_trigger_minutes' => 60,  // было 35 — слишком низко
    'lunch_deduct_minutes'  => 95,  // оставляем как у тебя (раз попадает в табель Пугиной)
    'lunch_max_minutes'     => 180, // защита, чтобы не ловить странные огромные паузы


    // perimeter (если используете в index)
    'perimeter' => [
        'match' => 'contains',
        'doors' => [],
    ],
];
