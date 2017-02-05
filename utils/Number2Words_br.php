<?php
function number_to_words($number) {
    
    $hyphen      = ' e ';
    $conjunction = ' e ';
    $separator   = ', ';
    $negative    = 'negativo ';
    $decimal     = ' com ';
    $dictionary  = array(
        0                   => 'zero',
        1                   => 'um',
        2                   => 'dois',
        3                   => 'três',
        4                   => 'quatro',
        5                   => 'cinco',
        6                   => 'seis',
        7                   => 'sete',
        8                   => 'oito',
        9                   => 'nove',
        10                  => 'dez',
        11                  => 'onze',
        12                  => 'doze',
        13                  => 'treze',
        14                  => 'catorze',
        15                  => 'quinze',
        16                  => 'dezesseis',
        17                  => 'dezessete',
        18                  => 'dezoito',
        19                  => 'dezenove',
        20                  => 'vinte',
        30                  => 'trinta',
        40                  => 'quarenta',
        50                  => 'cinqüenta',
        60                  => 'sessenta',
        70                  => 'setenta',
        80                  => 'oitenta',
        90                  => 'noventa',
        100                 => 'cento',
        200                 => 'duzentos',
        300                 => 'trezentos',
        400                 => 'quatrocentos',
        500                 => 'quinhentos',
        600                 => 'seiscentos',
        700                 => 'setecentos',
        800                 => 'oitocentos',
        900                 => 'novecentos',
        1000                => 'mil',
        1000000             => 'milhão',
        1000000000          => 'bilhão',
        1000000000000       => 'trilhão',
        1000000000000000    => 'quatrilhão',
        1000000000000000000 => 'quintilhão'
    );
    
    if (!is_numeric($number)) {
        return false;
    }
    
    if (($number >= 0 && (int) $number < 0) || (int) $number < 0 - PHP_INT_MAX) {
        // overflow
        trigger_error(
            'number_to_words only accepts numbers between -' . PHP_INT_MAX . ' and ' . PHP_INT_MAX,
            E_USER_WARNING
        );
        return false;
    }

    if ($number < 0) {
        return $negative . number_to_words(abs($number));
    }
    
    $string = $fraction = null;
    
    if (strpos($number, '.') !== false) {
        list($number, $fraction) = explode('.', $number);
    }
       
    switch (true) {
        case $number < 21:
            $string = $dictionary[$number];
            break;
        case $number < 100:
            $tens   = ((int) ($number / 10)) * 10;
            $units  = $number % 10;
            $string = $dictionary[$tens];
            if ($units) {
                $string .= $hyphen . $dictionary[$units];
            }
            break;
        case $number < 1000:
            $remainder = $number % 100;
            $hundreds= 1000 - ((1000 + $remainder) - $number); 
            $string = $dictionary[$hundreds];
            if ($remainder) {
                $string .= $conjunction . number_to_words($remainder);
            }
            break;
        default:
            $baseUnit = pow(1000, floor(log($number, 1000)));
            $numBaseUnits = (int) ($number / $baseUnit);
            $remainder = $number % $baseUnit;
            $string = number_to_words($numBaseUnits) . ' ' . $dictionary[$baseUnit];
            if ($remainder) {
                $string .= $remainder < 100 ? $conjunction : $separator;
                $string .= number_to_words($remainder);
            }
            break;
    }
    
    if (null !== $fraction && is_numeric($fraction)) {
        $string .= $decimal;
        $words = array();
            switch (true) {
                case $fraction < 10:
                    $tens   = substr($fraction, -1);
                    $words[] = $dictionary[$tens];
                    break;
                case $fraction < 21:
                    $words[] = $dictionary[$fraction];
                    break;
                case $fraction < 100:
                    $tens   = ((int) ($fraction / 10)) * 10;
                    $units  = $fraction % 10;
                    $words[] = $dictionary[$tens];
                    if ($units) {
                        $words[] .= $hyphen . $dictionary[$units];
                    }
                    break;
            }
        $string .= implode(' ', $words)." centavos";
    }
    
    return $string;
}

//$num=1246;
//
//echo $num.": ".number_to_words($num)."<br>";
//echo "473.03: ".number_to_words(473.03)."<br>";
//echo "15872.84: ".number_to_words(15872.84)."<br>";

?>
