<?php
class INCI {
    /* Properties */
    public string $file;
    public array $data;
    public array $dictionary;
    public array $properties;
    public bool $extended;
    /* Methods */
    public function __construct(protected string $filename) {
        if (!file_exists($filename)) throw new Exception("This file does not exist");
        $this->file = $filename;
        $filetype = pathinfo($filename);
        if ($filetype["extension"] == "csv") {
            $csvarray = array_map("str_getcsv",file($filename));
            $this->properties = $csvarray[0];
            foreach ($csvarray as $lk => $lv) {
                if ($lk == 0) continue;
                $templine = [];
                foreach ($lv as $ck => $cv) {
                    $templine[$this->properties[$ck]] = $cv;
                }
                if (!empty($templine['function'])) $templine['function'] = explode(" | ",$templine['function']);
                $this->data[$templine['inci']] = $templine;
            }
        } elseif ($filetype["extension"] == "json") {
            $this->data = json_decode(file_get_contents($filename,FILE_IGNORE_NEW_LINES),true);
            $this->properties = array_keys($this->data[array_rand($this->data)]);
        } else {
            throw new Exception("Wrong type of input file (required csv or json)");
        }
        $this->dictionary = array_keys($this->data);
        $extendedprops = ["description", "sccs", "gif"];
        $this->extended = (count(array_intersect($this->properties,$extendedprops)) > 0) ? true : false;
        // $this->extended = true; // For debuging
    }
    public function get(string $inciname, string $property): string | array | null {
        $inciname = strtoupper($inciname);
        if (!array_key_exists($inciname,$this->data)) return null;
        if (!in_array($property,$this->properties)) return null;
        return $this->data[$inciname][$property];
    }
    public function suggest(string $mistake, int $startsimilarity = 75) : array | null{
        $mistake = strtoupper($mistake);
        $attempt = 1;
        while ($attempt <= 10) {
            $suggestions = [];
            $perclimit = $startsimilarity - ($attempt - 1) * 5;
            $rawsuggest = array_filter($this->dictionary,function($value) use ($mistake,$perclimit) {
                similar_text($mistake,$value,$perc);
                if ($perc >= $perclimit) return true;
            });
            foreach ($rawsuggest as $s) {
                similar_text($mistake,$s,$perc);
                $suggestions[] = [
                    "inci" => $s,
                    "similarity" => round($perc,2)
                ];
            }
            array_multisort(array_column($suggestions,"similarity"),SORT_DESC,$suggestions);
            if (count($suggestions) >= 3) return $suggestions;
            $attempt++;
        }
        return null;
    }
    public function check(string $inci) : bool {
        $inci = strtoupper($inci);
        return in_array($inci,$this->dictionary);
    }
    public function isprop(string $parameter) : bool {
        return in_array($parameter,$this->properties);
    }
}

function printtable(array $array, string $tableclass = null): string {
    // Small function to put visually data into table
    $result = is_null($tableclass) ? "<table style=\"border: 1px solid black; border-collapse: collapse;\">" : "<table class=\"$tableclass\">";
    foreach ($array as $line) {
        $result .= is_null($tableclass) ? "<tr style=\"border: 1px solid black;\">" : "<tr>";
        foreach ($line as $cell) {
            $result .= is_null($tableclass) ? "<td style=\"border: 1px solid black; padding: .5rem;\">$cell</td>" : "<td>$cell</td>";
        }
        $result .= "</tr>";
    }
    $result .= "</table>";
    return $result;
}

function lettersize(string $text) {
    $rp = json_decode(file_get_contents("replacetable.json"),true);
    $text = strtolower($text);
    $separators = [",",".","-","+","(",")"," ","/","&",":","'","•",";","\\","|","[","]"];
    // Check what separators are included in checked text
    foreach ($separators as $sep) {
        if (str_contains($text,$sep)) {
            $usedseps[] = $sep;
        }
    }
    // List positions of all separators
    $positions = array();
    if (!empty($usedseps)) {
        foreach ($usedseps as $sep) {
            $last = 0;
            while (($last = strpos($text,$sep,$last)) !== false) {
                $positions[] = $last;
                $last = $last + 1;
            }
        }
    }
    sort($positions);
    // Split string into words and serparators
    $seplen = count($positions);
    for ($i=0; $i < $seplen; $i++) {
        if (isset($positions[$i+1])) {
            $next = $positions[$i+1];
        } else {
            $next = strlen($text);
        }
        if ($i == 0 && $positions[0] != 0) {
            $split[] = substr($text,0,$positions[0]);
        }
        $split[] = substr($text,$positions[$i],1);
        if ($next-$positions[$i] != 1 ) {
            $split[] = substr($text,$positions[$i]+1,$next-$positions[$i]-1);
        }
    }
    if (empty($split)) $split[0] = $text;
    // Check for exceptions
    foreach ($rp['except'] as $exc => $table) {
        if (str_contains($text,$exc)) {
            foreach ($table as $tk => $tv) {
                $exceptions[$tk] = $tv;
            }
        }
    }
    // Make uppercase when needed
    foreach ($split as $part) {
        $partlen = strlen($part);
        if (array_key_exists($partlen,$rp) && array_key_exists($part,$rp[$partlen])) {
            if (isset($exceptions) && array_key_exists($part,$exceptions)) {
                $newpart[] = strtr($part,$exceptions);
            } else {
                $newpart[] = strtr($part,$rp[$partlen]);
            }
        } else {
            $newpart[] = ucfirst($part);
        }
    }
    // Return corrected 
    return implode($newpart);
}

function diff(string $string1, string $string2, string $opentag="<strong>", string $closetag="</strong>", &$matrix = null): string {
    // LCS algorithm
    $a1 = str_split($string1);
    $a2 = str_split($string2);
    $n1 = count($a1);
    $n2 = count($a2);
    $values = [];
    $mask = [];
    // Make first row and column of 0s
    for ($y=-1;$y<$n1;$y++) $matrix[$y][-1] = 0;
    for ($x=-1;$x<$n2;$x++) $matrix[-1][$x] = 0;
    // Fill the rest of matrix
    for ($y=0;$y<$n1;$y++) {
        for ($x=0;$x<$n2;$x++) {
            if ($a1[$y] == $a2[$x]) {
                $matrix[$y][$x] = $matrix[$y-1][$x-1] + 1;
            } else {
                $matrix[$y][$x] = max($matrix[$y-1][$x],$matrix[$y][$x-1]);
            }
        }
    }
    // Determine what is the same and different
    $y = $n1-1;
    $x = $n2-1;
    while ($y > -1 || $x > -1) {
        if ($x > -1 && $matrix[$y][$x-1] == $matrix[$y][$x]) {
            $values[] = $a2[$x];
            $mask[] = true;
            $x--;
            continue;
        }
        if ($y > -1 && $matrix[$y-1][$x] == $matrix[$y][$x]) {
            $values[] = $a1[$y];
            $mask[] = true;
            $y--;
            continue;
        }
        $values[] = $a1[$y];
        $mask[] = false;
        $y--;
        $x--;
    }
    $values = array_reverse($values);
    $mask = array_reverse($mask);
    // Show result as highlighted differences using open and close tag
    $pmc = 0;
    $result = "";
    foreach ($mask as $k => $mc) {
        if ($mc != $pmc) {
            if ($pmc) {
                $result .= $closetag;
            }
            if ($mc) {
                $result .= $opentag;
            }
        }
        $result .= $values[$k];
        $pmc = $mc;
    }
    if ($pmc) $result .= $closetag;

    return $result;
}
?>
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
// PHP Tools

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
    $inci = new INCI("../INCI.json");
    echo '<table class="table">';
    $exceptions = ["ETHYL CYSTEINATE HCL"];
    foreach ($inci->data as $line) {
        if (((substr_count($line["casNo"],"-") > 2 && !str_contains($line["casNo"]," / ")) || (substr_count($line["ecNo"],"-") > 2 && !str_contains($line["ecNo"]," / ")) || (str_contains($line["casNo"],";")) || (str_contains($line["ecNo"],";")) || (str_contains($line["casNo"],",")) || (str_contains($line["ecNo"],","))) && !in_array(strtoupper($line["inci"]),$exceptions)) {
            echo "<tr><td><span onclick=\"navigator.clipboard.writeText(this.innerText)\" class=\"user-select-all\">". $line["inci"] ."</span></td><td>". $line["casNo"] ."</td><td>". $line["ecNo"] ."</td></tr>";
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

// INCI comparison between csv and json
if (false) {
    $incicsv = new INCI("../INCI.csv");
    $incijson = new INCI("../INCI.json");
    if (false) {
        echo "<p>Nie występujące składniki w JSON</p>";
        echo "<table class=\"table table-sm\">";
        foreach ($incicsv->dictionary as $ing) {
            if (!in_array($ing,$incijson->dictionary)) {
                echo "<tr><td onclick=\"navigator.clipboard.writeText(this.innerText)\" class=\"user-select-all\">$ing</td><td>".$incicsv->get($ing,"refNo")."</td></tr>";
                if (!empty($incicsv->get($ing,"refNo"))) $toadd[] = $incicsv->get($ing,"refNo");
            }
        }
        echo "</table>";
        $file = fopen("todoagain.json","w");
        fwrite($file,json_encode($toadd));
        fclose($file);
    }
    if (true) {
        echo "<table class=\"table table-sm\">";
        echo "<tr><th scope=\"col\">INCI</th><th scope=\"col\">CAS CSV</th><th scope=\"col\">CAS JSON</th><th scope=\"col\">EC CSV</th><th scope=\"col\">EC JSON</th><th scope=\"col\">ANX CSV</th><th scope=\"col\">ANX JSON</th></tr>";
        foreach ($incicsv->data as $ingredient) {
            if ($incijson->check($ingredient["inci"]) && ($ingredient["casNo"] != $incijson->get($ingredient["inci"],"casNo") || $ingredient["ecNo"] != $incijson->get($ingredient["inci"],"ecNo") || $ingredient["anx"] != $incijson->get($ingredient["inci"],"anx"))) {
                echo "<tr><td onclick=\"navigator.clipboard.writeText(this.innerText)\" class=\"user-select-all\">".$ingredient["inci"]."</td><td>".$ingredient["casNo"]."</td><td>".$incijson->get($ingredient["inci"],"casNo")."</td><td>".$ingredient["ecNo"]."</td><td>".$incijson->get($ingredient["inci"],"ecNo")."</td><td>".$ingredient["anx"]."</td><td>".$incijson->get($ingredient["inci"],"anx")."</td></tr>";
            }
        }
        echo "</table>";
    }
}

?>
</div>
</body>
</html>