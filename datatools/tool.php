<?php
// This is a tool to update INCI.csv

if (false) {
    // This is manual filler of INCI file

    $file = "INCI.csv";
    // Input data
    $addanx = "II/411";
    $lookfor = "28446 | 28447 | 33130 | 33131 | 33132 | 33133 | 33134 | 33135 | 33136 | 33137 | 33138 | 33139 | 33141 | 33142 | 33143 | 33145 | 33146 | 33147 | 33148 | 33149 | 33150 | 33151 | 33152 | 33153 | 33154 | 33155 | 33264 | 33274 | 33287 | 33530 | 33531 | 55020 | 55591 | 84554 | 84555 | 84556 | 84557 | 84560 | 84638 | 84972 | 84973 | 84974 | 84975 | 85021 | 85035 | 85036 | 85069 | 85073 | 85140 | 88437";
    // Process above info
    // Open files
    $oldinci = array_map('str_getcsv',file($file,FILE_IGNORE_NEW_LINES));
    $updatedincifile = fopen($file,'w');
    // Explode lookfor data
    $lookforarray = explode(" | ",$lookfor);
    // MAGIC!
    foreach ($oldinci as $line) {
        if (in_array($line[5],$lookforarray) && empty($line[4])) {
            $line[4] = $addanx;
        }
        foreach ($line as $part) {
            if (!empty($part)) {
                $newline[] = '"'.$part.'"';
            } else {
                $newline[] = $part;
            }
        }
        $linetowrite = implode(",",$newline)."\n";
        unset($newline);
        fwrite($updatedincifile,$linetowrite);
    }
    fclose($updatedincifile);
    echo "DONE!";
}

if (false) {
    // This for adding from CosIng raw file

    $file = "../INCI.csv";
    $input = "cosing.json";
    $raw = "cosingraw.csv";
    // If cosing.json is older than cosing.csv then re-generate it
    if (!file_exists($input) || filemtime($raw) > filemtime($input)) {
        $f = array_map('str_getcsv',file($raw));
        foreach ($f as $line) {
            if ($line[8] == 'substance') continue;
            $cosing[$line[0]] = [
                'name' => $line[0],
                'desc' => $line[1],
                'casno' => $line[2],
                'ecno' => $line[3],
                'anx' => $line[4],
                'refno' => $line[5],
                'sccs' => array(
                    'links' => explode(' | ',$line[6]),
                    'names' => explode(' | ',$line[7])
                ),
                'identified' => explode(' | ',$line[9]),
                'function' => explode(' | ',$line[10]),
                'usinci' => $line[11]
            ];
        }
        file_put_contents($input,json_encode($cosing));
        echo "Generated new cosing.json";
    }
    // Get data from json file
    $data = json_decode(file_get_contents($input),true);
    // Get data from INCI file
    $oldinci = array_map('str_getcsv',file($file,FILE_IGNORE_NEW_LINES));
    $openfile = fopen($file,'w');
    foreach ($oldinci as $line) {
        if (array_key_exists($line[1],$data)) {
            if (empty($line[5])) {
                $line[5] = $data[$line[1]]['refno'];
            }
            if (empty($line[6])) {
                $line[6] = implode(" | ",$data[$line[1]]['function']);
            }
        } else {
            echo $line[1] . "<br>";
        }
        // Write to file
        foreach ($line as $part) {
            if (!empty($part)) {
                $newline[] = '"'.$part.'"';
            } else {
                $newline[] = $part;
            }
        }
        $linetowrite = implode(",",$newline)."\n";
        unset($newline);
        fwrite($openfile,$linetowrite);
    }
    fclose($openfile);
}

if (false) {
    // Extract unique functions of ingredients
    $input = "cosing.json";
    $data = json_decode(file_get_contents($input),true);
    $functions = array();
    foreach ($data as $ingredient) {
        foreach ($ingredient["function"] as $func) {
            if (!in_array($func,$functions) && !empty($func)) {
                $functions[] = $func;
            }
        }
    }
    asort($functions);
    // Show all functions
    foreach ($functions as $line) {
        echo $line."<br>";
        $tofile[$line] = ["pl"=>"", "en"=>ucfirst(strtolower($line))];
    }
    // Export to file
    $funcfile = "functions.json";
    file_put_contents($funcfile,json_encode($tofile));
}
?>