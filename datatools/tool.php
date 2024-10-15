<!DOCTYPE HTML>
<html lang="pl" data-bs-theme="dark">
<head>   
    <title>Tool script</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="Mikołaj Piętka">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="../favicon.ico">
</head>
<body class="bg-dark">
<div class="container-fluid">
<h1 class="m-3 mb-5">Tool for data modification</h1>
<?php
// Tool to update INCI.csv

// This is manual filler of INCI file
if (false) {

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

// This for adding from CosIng raw file
if (false) {

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
        if ($line[1] == "INCI") {
            fwrite($openfile,'"ID","INCI","CAS","WE","ANNEX","COSING REF. NO.","FUNCTIONS"'."\n");
            continue;
        }
        if (array_key_exists($line[1],$data)) {
            if (empty($line[5])) {
                $line[5] = $data[$line[1]]['refno'];
            }
            if (empty($line[6])) {
                if (empty($data[$line[1]]['function'])) {
                    $line[6] = "UNKNOWN";
                } else {
                    $line[6] = implode(" | ",$data[$line[1]]['function']);
                }
            }
        } else {
            $line[6] = "UNKNOWN";
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

// Extract unique functions of ingredients
if (false) {
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

// Generate info from annex 2 exported from CosIng
if (false) {
    $annexfile = "anx2-04102024.csv";
    $annex = array_map('str_getcsv',file($annexfile,FILE_IGNORE_NEW_LINES));
    // Generate json with found indentified ingredients
    $identified = array();
    foreach ($annex as $line) {
        // Skip header
        if ($line[0]=="Reference Number") continue;
        if (!empty($line[8])) {
            $singleid = explode(",",$line[8]);
            foreach($singleid as $id) {
                if (!array_key_exists($id,$identified) && !empty($id)) {
                    $identified[$id] = "II/" . $line[0];
                } elseif (!empty($id)) {
                    $identified[$id] .= ", II/" . $line[0];
                }
            }
        }
    }
    file_put_contents("anx2.json",json_encode($identified));
}

// Update INCI with generated and redacted file anx2.json
if (false) {
    $file = "../INCI.csv";
    $oldinci = array_map('str_getcsv',file($file,FILE_IGNORE_NEW_LINES));
    $anxjson = json_decode(file_get_contents("anx2.json"),true);
    $openfile = fopen($file,'w');
    foreach ($oldinci as $line) {
        if ($line[1] == "INCI") {
            fwrite($openfile,'"ID","INCI","CAS","WE","ANNEX","COSING REF. NO.","FUNCTIONS"'."\n");
            continue;
        }
        if (array_key_exists($line[1],$anxjson)) {
            if (empty($line[4])) {
                $line[4] = $anxjson[$line[1]];
            } elseif (!str_contains($line[4],$anxjson[$line[1]])) {
                echo $line[1] . "<br>";
            }
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

// List all CAS & WE numbers where are not separated by " / "
if (false) {
    $file = "../INCI.csv";
    $oldinci = array_map('str_getcsv',file($file,FILE_IGNORE_NEW_LINES));
    // $updatedincifile = fopen($file,'w');
    echo '<table class="table">';
    $exceptions = ["ETHYL CYSTEINATE HCL"];
    foreach ($oldinci as $line) {
        if (((substr_count($line[2],"-") > 2 && !str_contains($line[2]," / ")) || (substr_count($line[3],"-") > 2 && !str_contains($line[3]," / ")) || (str_contains($line[2],";")) || (str_contains($line[3],";")) || (str_contains($line[2],",")) || (str_contains($line[3],","))) && !in_array($line[1],$exceptions)) {
            echo "<tr><td><span onclick=\"navigator.clipboard.writeText(this.innerText)\" class=\"user-select-all\">". $line[1] ."</span></td><td>". $line[2] ."</td><td>". $line[3] ."</td></tr>";
        }
    }
    echo '</table>';
}

// Convert echa520.csv into json
if (false) {
    $file = "echa520.csv";
    $data = array_map("str_getcsv",file($file,FILE_IGNORE_NEW_LINES));
    $listtoconvert = array_column($data,0);
    sort($listtoconvert);
    file_put_contents("../echa520.json",json_encode($listtoconvert));
}
?>
</div>
</body>
</html>