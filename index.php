<?php 
setlocale(LC_ALL,'pl_PL');
date_default_timezone_set('Europe/Warsaw');
if (!isset($_GET["debug"])) error_reporting(0);

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
        // $this->extended = (count(array_intersect($this->properties,$extendedprops)) > 0) ? true : false;
        $this->extended = true;
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

if (!empty($_GET['lettersize'])) {
    $text = urldecode($_GET['lettersize']);
    $array = ["from" => $text, "converted" => lettersize($text)];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($array);
    exit;
}

if (isset($_GET['debug']) && strtolower($_GET['debug']) == "test") {
    try {
        // Tests should be here
        $x = "abcdefghi";
        $y = "abedfghkii";
        $xs = str_split($x);
        $ys = str_split($y);
        $test = diff($x,$y,"<strong>","</strong>",$table);
        array_unshift($ys,"");
        array_unshift($table,$ys);
        array_unshift($xs,"","");
        $i = 0;
        $table = array_map(function($line) use ($xs,&$i) {
            array_unshift($line,$xs[$i]);
            $i++;
            return $line;
        },$table);
        echo printtable($table);
        echo $test;
    } catch (Exception $e) {
        echo $e->getMessage();
    }
    exit;
}

if (isset($_GET['micro'])) {
    // Microplastics response for JS request in modal (whole and searched/filtered due to slow JS reaction)
    $echa520 = json_decode(file_get_contents("echa520.json",true));
    foreach ($echa520 as $ing) {
        if (str_contains(strtolower($ing),urldecode($_GET['micro']))) {
            echo '<li class="list-group-item user-select-all" ondblclick=(copyText(this))>' . lettersize($ing) . '</li>'; 
        }
    } 
    exit;
}

if (isset($_GET['anx'])) {
    // Response to annex request
    $request = urldecode($_GET['anx']);
    if (str_contains($request,',')) {
        $annexes = explode(', ',$request);
    } else {
        $annexes[] = $request;
    }
    // Choose right file and title for each annex
    foreach ($annexes as $anx) {
        $annex = explode('/',$anx);
        switch ($annex[0]) {
            case "II":
                $anxfile = "A2.csv";
                $anxtitle = "Załącznik II: Wykaz substancji zakazanych w produktach kosmetycznych";
                break;
            case "III":
                $anxfile = "A3.csv";
                $anxtitle = "Załącznik III: Wykaz substancji, które mogą być zawarte w produktach kosmetycznych wyłącznie z zastrzeżeniem określonych ograniczeń";
                break;
            case "IV":
                $anxfile = "A4.csv";
                $anxtitle = "Załącznik IV: Wykaz barwników dopuszczonych w produktach kosmetycznych";
                break;
            case "V":
                $anxfile = "A5.csv";
                $anxtitle = "Załącznik V: Wykaz substancji konserwujących dozwolonych w produktach kosmetycznych";
                break;
            case "VI":
                $anxfile = "A6.csv";
                $anxtitle = "Załącznik VI: Wykaz substancji promieniochronnych dozwolonych w produktach kosmetycznych";
                break;
        }
        if (!file_exists($anxfile) || empty($fileraw = array_map('str_getcsv', file($anxfile)))) {
            echo 'Błąd odczytu pliku! Odśwież stronę i spróbuj ponownie';
            exit;
        }
        // Show response - whole and for each position 
        echo "<h3>" . $anxtitle . "</h3>";
        echo "<table class=\"table mt-2\">";
        if ($annex[1] == "all"): ?>
            <thead>
                <tr>
                    <?php foreach ($fileraw[0] as $cell) echo '<th scope="col">' . $cell . '</th>'; ?>
                </tr>
            </thead>
            <tbody class="table-group-divider">
            <?php
                foreach ($fileraw as $key => $row) {
                    if ($key == 0) continue;
                    echo '<tr>';
                    foreach ($row as $cell) {
                        echo '<td>'. $cell .'</td>';
                    }
                    echo '</tr>';
                }
            ?>
        <?php else: ?>
            <thead>
                <tr>
                    <th scope="col" class="col-4">Kolumna</th>
                    <th scope="col" class="col-8">Treść</th>
                </tr>
            </thead>
            <tbody class="table-group-divider">
                <?php
                // Convert annex array so ids are keys
                $anxfileconv = array_combine(array_column($fileraw,0),$fileraw);
                foreach ($fileraw[0] as $key => $cell) { ?>
                    <tr>
                        <th scope="row"><?php echo $cell; ?></th>
                        <td><?php echo $anxfileconv[$annex[1]][$key]; ?></td>
                    </tr>
                <?php } ?>
            </tbody>
        <?php endif;
        echo "</table>";
    }
    exit;
}

if (!empty($_POST['inci']) || (!empty($_POST['inci-model']) && !empty($_POST['inci-compare'])) || isset($_GET['random']) || isset($_GET['details'])) {
    try {
        $inci = new INCI("INCI.csv");
        $funcdict = json_decode(file_get_contents('functions.json'),true);
    } catch (Exception $e) {
        echo "Wystąpił błąd, odśwież stronę i spróbuj ponownie";
        exit;
    }
}

if (!empty($_GET['details'])) {
    // Response for ingredient details
    $ingredientname = urldecode($_GET['details']);
    if (!empty($inci->get($ingredientname,'description'))){
        echo '<h3>Opis</h3>';
        echo '<p>' . $inci->get($ingredientname,'description')['pl'] . '</p>';
        if ($inci->isprop('gif') && $inci->get($ingredientname,'gif')) echo '<img src="img/'.$inci->get($ingredientname,"refNo").'.gif">';
        echo '<hr>';
    }
    ?>
    <div class="m-3">
        <h3>Linki do wyszukania składnika</h3>
        <div class="mt-3 row g-3 row-cols-1 row-cols-lg-3">
            <div class="col">
                <a class="btn btn-outline-danger w-100" target="_blank" href="https://www.ulprospector.com/en/eu/PersonalCare/search?k=<?php echo $_GET['details']; ?>">ulProspector</a>
            </div>
            <div class="col">
                <a class="btn btn-outline-info w-100" target="_blank" href="https://cosmileeurope.eu/pl/inci/skladnik/?q=<?php echo $_GET['details']; ?>">COSMILE</a>
            </div>
            <div class="col">
                <a class="btn btn-outline-primary w-100" target="_blank" href="https://ec.europa.eu/growth/tools-databases/cosing/details/<?php echo $inci->get($ingredientname,"refNo"); ?>">CosIng</a>
            </div>
        </div>
    </div>
    <?php
    exit;
}

if (!empty($_POST['inci'])) {
    // Different separators
    if ($_POST['separator'] == "difsep") {
        $mainseparator = " " . trim($_POST['difsep']) . " ";
    } else {
        $mainseparator = $_POST['separator'];
    }
    // Connector space or separator
    if (isset($_POST['connector'])) {
        $connector = $mainseparator;
    } else {
        $connector = " ";
    }
    $inciexp = explode($mainseparator,str_replace(array("\r\n", "\n", "\r"),$connector,$_POST['inci']));
    foreach ($inciexp as $ingredient) {
        if (empty($ingredient)) continue;
        if (str_contains($ingredient,"(nano)") && !str_contains($ingredient," (nano)")) {
            $incitest[] = lettersize(trim(str_replace("(nano)"," (nano)",$ingredient)));
        } else {
            $incitest[] = lettersize(trim($ingredient));
        }
    }
    // Recreate ingredients with correct lettersize
    $recreate = implode($mainseparator,$incitest);
    $fail = 0;
    foreach ($incitest as $ingredient) { 
        // Cut-off nano part and check if ingredient is correct
        $temping = trim(str_replace("(nano)","",$ingredient));
        if (!$inci->check($temping)) $fail = 1;
    }
    // Check for duplicates
    $counted = array_count_values(array_map('strtoupper',$incitest));
    foreach ($counted as $key => $value) {
        if ($value > 1) $duplicates[] = $key;
    }
}

// Make array with additional parameters
if (isset($_GET['additional']) && isset($_POST['inci'])) $options = $_POST['options'];

// Comparing mode
if (!empty($_POST['inci-model']) && !empty($_POST['inci-compare'])) {
    // Remove double spaces and eol for both inci inputs
    $incimodel = str_replace(["\r\n", "\n", "\r", "  "]," ",$_POST["inci-model"]);
    $incicompare = str_replace(["\r\n", "\n", "\r", "  "]," ",$_POST["inci-compare"]);
    // Check if both strings are the same (case-insensitive)
    if (strcasecmp($incimodel,$incicompare) == 0) {
        $comparison = true;
    } else {
        $comparison = false;
        // $marked = showdifferences($incimodel,$incicompare);
        $marked = diff($incimodel,$incicompare,'<span class="text-danger">','</span>');
    }
    // Different separators
    if ($_POST['separator'] == "difsep") {
        $mainseparator = " " . trim($_POST['difsep']) . " ";
    } else {
        $mainseparator = $_POST['separator'];
    }
    // Exploded inci-model to analyze ingredients
    $inciexp = explode($mainseparator,$_POST['inci-model']);
    foreach ($inciexp as $ingredient) {
        if (empty($ingredient)) continue;
        if (str_contains($ingredient,"(nano)") && !str_contains($ingredient," (nano)")) {
            $incitest[] = lettersize(trim(str_replace("(nano)"," (nano)",$ingredient)));
        } else {
            $incitest[] = lettersize(trim($ingredient));
        }
    }
    $fail = 0;
    foreach ($incitest as $ingredient) { 
        // Cut-off nano part and check if ingredient is correct
        $temping = trim(str_replace(" (nano)","",$ingredient));
        if (!$inci->check($temping)) {
            $fail = 1;
        }
    }
    // Check for duplicates
    $counted = array_count_values(array_map('strtoupper',$incitest));
    foreach ($counted as $key => $value) {
        if ($value > 1) {
            $duplicates[] = $key;
        }
    }
}
// Showing random ingredient for testing
if (isset($_GET['random'])) {
    if (!empty($_GET['random']) && is_numeric($_GET['random'])) {
        $rndnum = intval($_GET['random'],10);
    } else {
        $rndnum = 1;
    }
    $incitest = array_rand(array_flip($inci->dictionary),$rndnum);
    if (is_string($incitest)) $incitest = array($incitest);
    $fail = false;
}

// Get exchange rates from today's NBP table A
$jsoneur = json_decode(file_get_contents("https://api.nbp.pl/api/exchangerates/rates/a/eur/?format=json"),true);
$exeur = round($jsoneur['rates'][0]['mid'],2);
$jsonusd = json_decode(file_get_contents("https://api.nbp.pl/api/exchangerates/rates/a/usd/?format=json"),true);
$exusd = round($jsonusd['rates'][0]['mid'],2);
$exratedate = $jsoneur['rates'][0]['effectiveDate'];
?>
<!DOCTYPE HTML>
<html lang="pl" data-bs-theme="dark">
<head>   
    <title>Sprawdzanie INCI | <?php echo $_SERVER['HTTP_HOST']; ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="Mikołaj Piętka">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Page CSS -->
    <link href="styles.css?ver=2.6.inci" rel="stylesheet">
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
</head>
<body class="bg-dark">
    <nav class="container navbar navbar-expand-lg bg-body-tertiary my-3 border rounded-3 px-3 py-1">
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbar">
            <div class="navbar-nav nav-underline">
                <a href="index.php" class="nav-link<?php if (empty($_GET)) echo " active"; ?>">Weryfikacja</a>
                <a href="?compare" class="nav-link<?php if (isset($_GET['compare'])) echo " active"; ?>">Porównanie</a>
                <a href="#annex" data-bs-toggle="modal" class="nav-link">Załączniki</a>
                <a href="#info" data-bs-toggle="modal" class="nav-link">Informacje</a>
                <a href="#microplastics" data-bs-toggle="modal" class="nav-link">ECHA-520</a>
                <a href="#currency" data-bs-toggle="modal" class="nav-link">Kursy walut</a>
                <a href="?additional" class="nav-link visually-hidden<?php if (isset($_GET['additional'])) echo " active"; ?>">Dodatkowe opcje</a>
                <a href="https://ec.europa.eu/growth/tools-databases/cosing/" target="_blank" class="nav-link">CosIng<i class="ms-2 bi bi-box-arrow-up-right"></i></a>
            </div>
        </div>
    </nav>
    <div class="container my-3">
        <?php if (!isset($_GET['compare'])): ?>
        <h2>Sprawdzanie INCI</h2>
        <h6>Weryfikacja poprawności składu ze słownikiem wspólnych nazw składników (INCI) <sup><span class="text-info" data-bs-toggle="tooltip" data-bs-title="Więcej szczegółów w odnośniku Informacje"><i class="bi bi-info-circle"></i></span></sup></h6>
        <?php else: ?>
        <h2>Porównanie składów i weryfikacja</h2>
        <h6>Porównanie nie koryguje składów! (wielkość liter, spacje itp.)</h6>
        <?php endif; ?>
        <form method="post" <?php if (isset($_GET['random'])) echo 'action="index.php"'; ?>>
            <?php if (!isset($_GET['compare'])): ?>
            <textarea class="form-control" id="inci" name="inci" <?php if (isset($_GET['random']) || isset($_GET['alling'])) echo 'rows="1"'; else echo 'rows="12"'; if (!isset($recreate) && !isset($_GET['random'])) echo " autofocus"; ?>><?php if (isset($recreate)) echo $recreate; ?></textarea>
            <?php else: ?>
            <div class="row row-cols-1 row-cols-lg-2 g-3">
                <div class="col">
                    <h5>Zaakceptowany skład</h5>
                    <textarea class="form-control" rows="9" id="inci-model" name="inci-model" <?php if (empty($_POST['inci-model'])) echo "autofocus"; ?> required><?php if (!empty($incimodel)) echo $incimodel; ?></textarea>
                </div>
                <div class="col">
                    <h5>Skład do porównania</h5>
                    <textarea class="form-control" rows="9" id="inci-compare" name="inci-compare" required><?php if (!empty($incicompare)) echo $incicompare; ?></textarea>
                </div>
            </div>
            <?php endif; ?>
            <div class="row row-cols-lg-3 row-cols-1 g-3 mt-2">
                <div class="col">
                    <button type="submit" class="btn btn-outline-light w-100" id="submit"><i class="bi bi-check2-square"></i> Sprawdź</button>
                </div>
                <div class="col">
                    <button type="button" class="btn btn-outline-danger w-100" onclick="cleartextarea()"><i class="bi bi-trash3-fill"></i> Wyczyść</button>
                </div>
                <div class="col">
                    <button type="button" class="btn btn-outline-success w-100<?php if (empty($_POST['inci']) || isset($_GET['compare'])) echo " disabled"; ?>" onclick="ctrlz()"><i class="bi bi-arrow-counterclockwise"></i> Cofnij zmiany</button>
                </div>
                <div class="btn-group col" role="group">
                    <input type="checkbox" class="btn-check" name="connector" id="connector" <?php if (isset($_POST['connector'])) echo "checked"; if (isset($_GET['compare'])) echo "disabled"; ?>>
                    <label class="btn btn-outline-primary" for="connector">Zamień <i class="bi bi-arrow-return-left"></i> na separator</label>
                </div>
                <div class="col">
                    <select class="form-select" name="separator" id="separator">
                        <option value=", " <?php if ((isset($_POST['separator']) && $_POST['separator'] == ", ") || !isset($_POST['separator'])) echo "selected"; ?>>Separator: ","</option>
                        <option value=" • " <?php if (isset($_POST['separator']) && $_POST['separator'] == " • ") echo "selected"; ?>>Separator: "•"</option>
                        <option value=" (and) " <?php if (isset($_POST['separator']) && $_POST['separator'] == " (and) ") echo "selected"; ?>>Separator: "(and)"</option>
                        <option value="difsep" <?php if (isset($_POST['separator']) && $_POST['separator'] == "difsep") echo "selected"; ?>>Inny</option>
                    </select>
                </div>
                <div class="col">
                    <input type="text" class="form-control" name="difsep" id="difsep" placeholder="Inny separator" <?php if (!empty($_POST['difsep']) && (isset($_POST['separator']) && $_POST['separator'] == "difsep")) echo 'value="' . $_POST['difsep'] . '"'; if (!(isset($_POST['separator']) && $_POST['separator'] == "difsep")) echo " disabled" ?>>
                </div>
            </div>
        </form>
    </div>
    <div class="container-fluid ingredients">
        <?php if (isset($incitest)): 
        if ($fail): ?>
            <h3 class="text-danger fw-bold my-2 ms-5">Błędne INCI <i class="bi bi-emoji-frown-fill"></i></h3>
        <?php elseif (empty($duplicates)):?>
            <h3 class="text-success fw-bold my-2 ms-5">Poprawne INCI <i class="bi bi-hand-thumbs-up-fill"></i></h3>
        <?php else: ?>
            <h3 class="text-warning fw-bold my-2 ms-5">INCI zawiera powtórzenia <i class="bi bi-exclamation-triangle"></i></h3>
        <?php endif; 
        if (!empty($_POST['inci-model']) && !empty($_POST['inci-compare'])):  if ($comparison): ?>
            <h3 class="text-success fw-bold my-2 ms-5">Indentyczne składy <i class="bi bi-hand-thumbs-up-fill"></i></h3>
        <?php else: ?>
            <h3 class="text-danger fw-bold my-2 ms-5">Składy nie są Indentyczne <i class="bi bi-emoji-frown-fill"></i></h3>
            <div class="d-flex gap-2 mx-5"><strong class="text-danger">Różnice:</strong><span><?php echo $marked; ?></span></div>
        <?php endif; endif; ?>
        <div class="m-4">
            <button type="button" class="btn btn-sm btn-outline-light my-2" onclick="downloadTable()"><i class="bi bi-download"></i> Pobierz tabelę</button>
            <div class="table-responsive-md">
                <table class="table table-hover table-sm align-middle caption-top">
                    <caption><?php if ($fail && !isset($_GET["compare"])) echo "Podwójne kliknięcia na podpowiedź powoduje zamianę błędnego składnika na zaznaczony."; else echo "Podwójne kliknięcie na tekst kopiuje go do schowka."; ?></caption>
                    <thead>
                        <tr>
                            <th scope="col" class="dwn">INCI</th>
                            <?php if ($fail): ?>
                            <th scope="col" class="col-10">Podpowiedzi składników</th>
                            <?php else: ?>
                            <th scope="col" class="dwn col-2">Nr CAS</th>
                            <th scope="col" class="dwn col-2">Nr WE <sup><span class="text-info" data-bs-toggle="tooltip" data-bs-title="Inne nazwy numeru WE: EC number / EINECS / ELINCS / No-longer polymers"><i class="bi bi-info-circle"></i></span></sup></th>
                            <th scope="col" class="col-1">1223/2009</th>
                            <th scope="col" class="dwn col-2">Funkcja</th>
                            <th scope="col" class="dwn visually-hidden">Function</th>
                            <th scope="col" class="text-center col-1<?php if (!$inci->extended) echo " visually-hidden"; ?>">Szczegóły</th>
                            <th scope="col" class="text-center col-1<?php if ($inci->extended) echo " visually-hidden"; ?>">CosIng</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="table-group-divider">
                        <?php foreach ($incitest as $ingredient) { 
                            // If nano...
                            $temping = trim(str_replace(" (nano)","",$ingredient));
                            if ($inci->check($temping)) {
                                $test = true;
                            } else {
                                $test = false;
                                $suggestionsraw = $inci->suggest($temping);
                                $sugred = [];
                                foreach ($suggestionsraw as $s) {
                                    $sugred[] = (isset($_GET['compare'])) ? lettersize($s["inci"]) : '<span class="user-select-all nowrap" data-bs-toggle="tooltip" data-bs-title="Podobieństwo: '.$s["similarity"].'%" ondblclick="correctmistake(this)">' . lettersize($s["inci"]) . '</span>';
                                }
                                $suggestions = implode($mainseparator,$sugred);
                            }
                        ?>
                            <tr>
                                <th scope="row"  class="dwn<?php if (!$test) echo ' text-danger'; if ($test && !empty($duplicates) && in_array(strtoupper($ingredient),$duplicates)) echo ' text-warning'; ?>"><span class="user-select-all" ondblclick="copyText(this)"><?php echo lettersize($ingredient); ?></span></th>
                                <?php if ($fail): ?>
                                <td class="font-sm"><?php if (!$test) echo $suggestions; ?></td>
                                <?php else: ?>
                                <td class="dwn"><?php foreach (explode(" / ",$inci->get($temping,"casNo")) as $cas) $cases[] = '<span class="user-select-all font-monospace nowrap" ondblclick="copyText(this)">' .$cas. '</span>'; echo implode(" / ",$cases); unset($cases); ?></td>
                                <td class="dwn"><?php foreach (explode(" / ",$inci->get($temping,"ecNo")) as $we) $wes[] = '<span class="user-select-all font-monospace nowrap" ondblclick="copyText(this)">' .$we. '</span>'; echo implode(" / ",$wes); unset($wes); ?></td>
                                <td><?php 
                                    if (str_contains($inci->get($temping,"anx"),"I/") || str_contains($inci->get($temping,"anx"),"V/")) {
                                        if (str_contains($inci->get($temping,"anx"),'#')) {
                                            echo '<a href="#ingredient" class="text-reset" data-bs-toggle="modal">'. trim(substr($inci->get($temping,"anx"),0,strpos($inci->get($temping,"anx"),'#'))) .'</a> '. substr($inci->get($temping,"anx"),strpos($inci->get($temping,"anx"),'#'));
                                        } else {
                                            echo '<a href="#ingredient" class="text-reset" data-bs-toggle="modal">'. $inci->get($temping,"anx") .'</a>';
                                        }
                                    } else {
                                        echo $inci->get($temping,"anx"); 
                                    }
                                ?></td>
                                <td class="dwn"><?php foreach ($inci->get($temping,"function") as $function) {$ingfunc[] = $funcdict[$function]['pl']; }; echo implode(", ",array_map(function ($txt) {return'<span class="user-select-all" ondblclick="copyText(this)">' . $txt . '</span>'; },$ingfunc)); unset($ingfunc); ?></td>
                                <td class="dwn visually-hidden"><?php foreach ($inci->get($temping,"function") as $function) {$ingfunc[] = $funcdict[$function]['en']; }; echo implode(", ",$ingfunc); unset($ingfunc); ?></td>
                                <td class="text-center<?php if (!$inci->extended) echo " visually-hidden"; ?>"><a class="text-reset link-underline link-underline-opacity-0" data-bs-toggle="modal" href="#details"><i class="bi bi-info-circle fs-5"></i></a></td>
                                <td class="text-center<?php if ($inci->extended) echo " visually-hidden"; ?>"><?php if (!empty($inci->get($temping,"refNo"))) echo '<a class="text-reset link-underline link-underline-opacity-0" target="_blank" title="Link do składnika w CosIng" href="https://ec.europa.eu/growth/tools-databases/cosing/details/'.$inci->get($temping,"refNo").'"><i class="bi bi-info-circle"></i></a>';?></td>
                                <?php endif; ?>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="modal fade" id="ingredient" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered modal-fullscreen-lg-down modal-lg">
            <div class="modal-content">
                <div class="modal-header fst-italic">
                    <h2 class="modal-title"></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="annexes">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Ładowanie...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="details" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered modal-fullscreen-lg-down modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Szczegóły składnika <span class="fst-italic"></span></h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Ładowanie...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="annex" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-fullscreen modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Załączniki</h5>
                    <div class="col-2 mx-auto">
                        <select class="form-select" onchange="getAnnex(this.value)" name="query">
                            <option value="0" selected>Wybierz...</option>
                            <option value="II/all">Załącznik II</option>
                            <option value="III/all">Załącznik III</option>
                            <option value="IV/all">Załącznik IV</option>
                            <option value="V/all">Załącznik V</option>
                            <option value="VI/all">Załącznik VI</option>
                        </select>
                    </div>
                    <div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <div class="modal-body"></div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="microplastics" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Lista mikroplastików wg ECHA 520-scenario</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="search" class="form-control" placeholder="Zacznij wpisywać żeby wyszukać" id="search">
                    <p class="ms-2 mt-3"></p>
                    <ul class="list-group list-group-flush"></ul>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="currency" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Przelicznik walut</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Zgodnie z tabelą kursów walut NBP z dnia <span></span></p>
                    <h4>Euro €</h4>
                    <div class="row g-3 row-cols-1 row-cols-lg-2 mb-4">
                        <div class="col">
                            <label for="eur">EUR</label>
                            <input type="number" class="form-control" id="eur">
                        </div>
                        <div class="col">
                            <label for="plneur">PLN</label>
                            <input type="number" class="form-control" id="plneur">
                        </div>
                    </div>
                    <h4>Dolar $</h4>
                    <div class="row g-3 row-cols-1 row-cols-lg-2 mb-4">
                        <div class="col">
                            <label for="usd">USD</label>
                            <input type="number" class="form-control" id="usd">
                        </div>
                        <div class="col">
                            <label for="plnusd">PLN</label>
                            <input type="number" class="form-control" id="plnusd">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="info" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title">Informacje i aktualizacje</h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h3>Informacje</h3>
                    <p>Celem aplikacji jest weryfikacja składu zgodnie ze słownikiem wspólnych nazw składników kosmetycznych zgodnie z DECYZJĄ WYKONAWCZĄ KOMISJI (UE) 2022/677 z dnia 31 marca 2022 roku. <a href="https://eur-lex.europa.eu/legal-content/pl/TXT/?uri=CELEX%3A32022D0677" target="_blank"><i class="bi bi-box-arrow-up-right"></i></a><br>Dodatkowo aplikacja umożliwia rozpisanie i sprawdzenie wszystkich składników oraz wyszukanie szczegółów zawartych w bazie CosIng oraz załącznikach ROZPORZĄDZENIA (UE) 1223/2009. <a href="https://eur-lex.europa.eu/legal-content/PL/TXT/?uri=CELEX:02009R1223-20240424" target="_blank"><i class="bi bi-box-arrow-up-right"></i></a></p>
                    <p>Na stronie działają skróty klawiszowe: <br> <kbd>Ctrl + <i class="bi bi-arrow-return-left"></i></kbd> - skrót do przycisku Sprawdź <br> <kbd>Ctrl + Del</kbd> - skrót do przycisku Wyczyść</p>
                    <h3>Aktualizacje plików</h3>
                    <table class="table">
                        <tr>
                            <th scope="row">Aktualizacja bazy składników</th>
                            <td><?php echo date("d.m.Y H:i", filemtime('INCI.csv')); ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Aktualizacja załącznika II</th>
                            <td><?php echo date("d.m.Y H:i", filemtime('A2.csv')); ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Aktualizacja załącznika III</th>
                            <td><?php echo date("d.m.Y H:i", filemtime('A3.csv')); ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Aktualizacja załącznika IV</th>
                            <td><?php echo date("d.m.Y H:i", filemtime('A4.csv')); ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Aktualizacja załącznika V</th>
                            <td><?php echo date("d.m.Y H:i", filemtime('A5.csv')); ?></td>
                        </tr>
                        <tr>
                            <th scope="row">Aktualizacja załącznika VI</th>
                            <td><?php echo date("d.m.Y H:i", filemtime('A6.csv')); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="position-fixed start-50 translate-middle-x top-0 mt-5" style="z-index: 2000;">
        <div class="toast fade text-bg-light" role="alert" data-bs-delay="1200">
            <div class="toast-body fw-bold fs-6 text-center">
                <p class="mb-1"></p>
                <span class="fst-italic"></span>
            </div>
        </div>
    </div>
    <script>
        function cleartextarea() {
            const inci = document.querySelectorAll('textarea');
            inci.forEach(x => {
                x.innerText = '';
                x.value = '';
            })
            const connector = document.querySelector('#connector');
            connector.checked = false;
            const separator = document.querySelector('#separator');
            separator.selectedIndex = 0;
            const difsep = document.querySelector('#difsep');
            difsep.value = '';
            if (tofocus = document.querySelector("#inci")) {
                tofocus.focus();
            }
            if (tofocus = document.querySelector("#inci-model")) {
                tofocus.focus();
            }   
        }
        function copyText(span) {
            navigator.clipboard.writeText(span.innerText);
            const toast = document.querySelector('.toast');
            toast.querySelector('p').innerText = "Skopiowano do schowka:";
            toast.querySelector('span').innerText = span.innerText;
            toastOn = bootstrap.Toast.getOrCreateInstance(toast);
            toastOn.show();
            window.getSelection().removeAllRanges();
        }
        function downloadTable() {
            let tableRows = document.querySelectorAll('.ingredients tr');
            let csvRow = [];
            tableRows.forEach(x => {
                let tableCols = x.querySelectorAll('.dwn');
                let csvCol = [];
                tableCols.forEach(x => {
                    csvCol.push('"'+x.innerText+'"');
                });
                csvRow.push(csvCol.join(","));
            });
            let csvData = csvRow.join('\n');

            csvFile = new Blob([csvData],{type: "text/csv"});
            let tempLink = document.createElement("a");
            let d = new Date;
            tempLink.download = "Ingredients-" + d.getFullYear() + ((d.getMonth()+1 < 10) ? "0"+(d.getMonth()+1) : (d.getMonth()+1)) + ((d.getDate() < 10) ? "0"+d.getDate() : d.getDate()) + "-" + ((d.getHours() < 10) ? "0"+d.getHours() : d.getHours()) + ((d.getMinutes() < 10) ? "0"+d.getMinutes() : d.getMinutes()) + ((d.getSeconds() < 10) ? "0"+d.getSeconds() : d.getSeconds()) + ".csv";
            tempLink.href = window.URL.createObjectURL(csvFile);
            tempLink.style.display = "none";
            document.body.appendChild(tempLink);
            tempLink.click();
            tempLink.remove();
        }

        const annexModal = document.querySelector('#ingredient');
        if (annexModal) {
            annexModal.addEventListener('show.bs.modal',event => {
                const link = event.relatedTarget;
                const request = encodeURI(link.innerText);
                let inciName = link.parentElement.parentElement.querySelector('th').innerText;
                if (inciName.includes(" (nano)")) {
                    inciName = inciName.replace(" (nano)","");
                }
                annexModal.querySelector('.modal-title').innerText = inciName;
                const xhttp = new XMLHttpRequest();
                xhttp.onload = function () {
                    annexModal.querySelector('.annexes').innerHTML = xhttp.responseText;
                    const tooltipTriggerList = document.querySelectorAll("[data-bs-toggle='tooltip']");
                    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
                }
                xhttp.open('GET','?anx='+request);
                xhttp.send();
            });
            annexModal.addEventListener('hidden.bs.modal',event => {
                annexModal.querySelector('.annexes').innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Ładowanie...</span></div></div>';
            });
        }

        const tooltipTriggerList = document.querySelectorAll("[data-bs-toggle='tooltip']");
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));

        function getAnnex (request) {
            if (request != '0') {
                const modalBody = document.querySelector('#annex .modal-body');
                modalBody.innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Ładowanie...</span></div></div>';
                request = encodeURI(request);
                const xhttp = new XMLHttpRequest();
                xhttp.onload = function () {
                    modalBody.innerHTML = xhttp.responseText;
                    const tooltipTriggerList = document.querySelectorAll("[data-bs-toggle='tooltip']");
                    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
                }
                xhttp.open('GET','?anx='+request);
                xhttp.send();
            } else {
                document.querySelector('#annex .modal-body').innerHTML = '<h2>Wybierz załącznik...</h2>'
            }
        }

        const separator = document.querySelector("#separator");
        const difsep = document.querySelector("#difsep");
        if (separator) {
            separator.addEventListener("change",event => {
                if (separator.value == "difsep") {
                    difsep.disabled = false;
                } else {
                    difsep.disabled = true;
                }
            })
        }

        document.addEventListener("keydown",event=>{
            if (event.ctrlKey && event.keyCode === 13) {
                document.querySelector("#submit").click();
            }
            if (event.ctrlKey && event.keyCode === 46 && document.activeElement !== document.querySelector("#inci")) {
                cleartextarea();
            }
            if (event.keyCode === 27) {
                document.activeElement.blur();
            }
        })

        function correctmistake(span) {
            let textto = span.innerText;
            let textfrom = span.parentElement.parentElement.querySelector("th span").innerText.replace(" (nano)","");
            span.parentElement.querySelectorAll("span").forEach(x => {
                if (x.className == "user-select-all nowrap text-success") {
                    textfrom = x.innerText;
                }
                x.className = "user-select-all nowrap";
            })
            span.className += " text-success";
            const textareainci = document.querySelector("#inci,#inci-model");
            textareainci.value = textareainci.value.replace(textfrom,textto);
            window.getSelection().removeAllRanges();
            // Notification
            const toast = document.querySelector('.toast');
            toast.querySelector('p').innerText = "Zamieniono";
            toast.querySelector('span').innerHTML = textfrom + "<br>na<br>" + textto;
            toastOn = bootstrap.Toast.getOrCreateInstance(toast);
            toastOn.show();
        }

        function ctrlz() {
            const textarea = document.querySelector("#inci");
            const prevtext = <?php if (!empty($_POST['inci'])) echo json_encode($_POST['inci']); else echo '""'; ?>;
            textarea.value = prevtext;
            const separator = document.querySelector("#separator");
            const prevseparator = <?php if (!empty($_POST['separator'])) echo json_encode($_POST['separator']); else echo '", "'; ?>;
            separator.value = prevseparator;
            const difsep = document.querySelector("#difsep");
            const prevdifsep = <?php if (!empty($_POST['difsep'])) echo json_encode($_POST['difsep']); else echo '""'; ?>;
            difsep.value = prevdifsep;
            const connector = document.querySelector("#connector");
            const prevconnector = <?php if (isset($_POST['connector'])) echo "true"; else echo "false"; ?>;
            connector.checked = prevconnector;
        }

        const search = document.querySelector("#search");
        const microplastics = document.querySelector("#microplastics");
        search.addEventListener("input",event => {
            const request = search.value.toLowerCase();
            const xhttp = new XMLHttpRequest();
            xhttp.onload = function() {
                microplastics.querySelector("ul").innerHTML = xhttp.responseText;
                microplastics.querySelector("p").innerText = "Znalezionych składników: " + microplastics.querySelectorAll("li").length;
            }
            xhttp.open("GET","?micro="+encodeURI(request));
            xhttp.send();
        })
        if (microplastics) {
            microplastics.addEventListener("show.bs.modal",event => {
                const xhttp = new XMLHttpRequest();
                xhttp.onload = function() {
                    microplastics.querySelector("ul").innerHTML = xhttp.responseText;
                    microplastics.querySelector("p").innerText = "";
                }
                xhttp.open('GET',"?micro");
                xhttp.send();
                search.value = "";
            })
        }

        const eur = document.querySelector("#eur");
        const plneur = document.querySelector("#plneur");
        const usd = document.querySelector("#usd");
        const plnusd = document.querySelector("#plnusd");
        const currency = document.querySelector("#currency");
        const exdatespan = currency.querySelector(".modal-body p span");

        const exeur = <?php echo $exeur; ?>;
        const exusd = <?php echo $exusd; ?>;
        const exdate = "<?php echo $exratedate; ?>";

        eur.addEventListener("input",event => {
            plneur.value = (eur.value * exeur).toFixed(2);
        })
        plneur.addEventListener("input",event => {
            eur.value = (plneur.value / exeur).toFixed(2);
        })
        usd.addEventListener("input",event => {
            plnusd.value = (usd.value * exusd).toFixed(2);
        })
        plnusd.addEventListener("input",event => {
            usd.value = (plnusd.value / exusd).toFixed(2);
        })
        currency.addEventListener("show.bs.modal", event => {
            eur.value = (1).toFixed(2);
            plneur.value = exeur;
            usd.value = (1).toFixed(2);
            plnusd.value = exusd;
            exdatespan.innerText = exdate;
        });

        const detailsModal = document.querySelector("#details");
        if (detailsModal) {
            detailsModal.addEventListener("show.bs.modal", event => {
                const showlink = event.relatedTarget;
                const ingredient = showlink.parentElement.parentElement.querySelector("th").innerText.replace(" (nano)","");
                detailsModal.querySelector(".modal-header span").innerText = ingredient;
                const xhttp = new XMLHttpRequest();
                xhttp.onload = function() {
                    detailsModal.querySelector(".modal-body").innerHTML = xhttp.responseText;
                };
                xhttp.open('GET','?details='+encodeURI(ingredient));
                xhttp.send();
            })
            detailsModal.addEventListener("hidden.bs.modal", event => {
                detailsModal.querySelector(".modal-body").innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Ładowanie...</span></div></div>';
            })
        }
    </script>
</body>
</html>