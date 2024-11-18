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
        $this->extended = (count(array_intersect($this->properties,$extendedprops)) > 0) ? true : false;
    }
    public function get(string $inciname, string $property): string | array | null {
        $inciname = strtoupper($inciname);
        if (!array_key_exists($inciname,$this->data)) return null;
        if (!in_array($property,$this->properties)) return null;
        return $this->data[$inciname][$property];
    }
    public function suggest(string $mistake, int $startsimilarity = 80, int &$endpercent = null) : array | null {
        $mistake = strtoupper($mistake);
        $attempt = 1;
        while ($attempt <= 5) {
            $suggestions = [];
            $perclimit = $startsimilarity - ($attempt - 1) * 5;
            $rawsuggest = array_filter($this->dictionary,function($value) use ($mistake,$perclimit) {
                similar_text($mistake,$value,$perc);
                if ($perc >= $perclimit) return true;
            });
            foreach ($rawsuggest as $s) {
                similar_text($mistake,$s,$perc);
                $suggestions[] = [
                    "inci" => lettersize($s),
                    "similarity" => round($perc,2)
                ];
            }
            array_multisort(array_column($suggestions,"similarity"),SORT_DESC,$suggestions);
            $endpercent = $perclimit;
            if (count($suggestions) >= 3) return $suggestions;
            $attempt++;
        }
        return null;
    }
    public function search(string $query, array | string $wheretolook) : array {
        $found = [];
        if (is_string($wheretolook) && $this->isprop($wheretolook)) {
            $haystack = array_change_key_case(array_column($this->data,$wheretolook,"inci"),CASE_UPPER);
            if (is_array($haystack[array_key_first($haystack)])) {
                $haystack = array_map("implode",$haystack);
            }
            $result = array_filter($haystack,function($value) use ($query) {
                if (str_contains(strtoupper($value),strtoupper($query))) return true;
            });
            $found = array_merge($found,$result);
        }
        if (is_array($wheretolook)) {
            foreach ($wheretolook as $destination) {
                if ($this->isprop($destination)) {
                    $haystack = array_change_key_case(array_column($this->data,$destination,"inci"),CASE_UPPER);
                    if (is_array($haystack[array_key_first($haystack)])) {
                        $haystack = array_map("implode",$haystack);
                    }
                    $result = array_filter($haystack,function($value) use ($query) {
                        if (str_contains(strtoupper($value),strtoupper($query))) return true;
                    });
                    $found = array_merge($found,$result);
                }
            }
        }
        return array_keys($found);
    }
    public function check(string $inci) : bool {
        $inci = strtoupper($inci);
        return in_array($inci,$this->dictionary);
    }
    public function isprop(string $parameter) : bool {
        return in_array($parameter,$this->properties);
    }
}
// Small function to put visually data into table
function printtable(array $array, string $tableclass = null): string {
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
// Function to correct lettersize 
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
                $last++;
            }
        }
    }
    sort($positions);
    // Split string into words and serparators
    $seplen = count($positions);
    for ($i=0; $i < $seplen; $i++) {
        $next = (isset($positions[$i+1])) ? $positions[$i+1] : strlen($text);
        if ($i == 0 && $positions[0] != 0) $split[] = substr($text,0,$positions[0]);
        $split[] = substr($text,$positions[$i],1);
        if ($next-$positions[$i] != 1 ) $split[] = substr($text,$positions[$i]+1,$next-$positions[$i]-1);
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
        $newpart[] = (array_key_exists($partlen,$rp) && array_key_exists($part,$rp[$partlen])) ? ((isset($exceptions) && array_key_exists($part,$exceptions)) ? strtr($part,$exceptions) : strtr($part,$rp[$partlen])) : ucfirst($part);
    }
    // Return corrected 
    return implode($newpart);
}
// Function to mark differences between two strings
function diff(string $string1, string $string2, string $opentag="<strong>", string $closetag="</strong>", &$matrix = null): string {
    // LCS algorithm
    $a1 = str_split($string1);
    $a2 = str_split($string2);
    $n1 = count($a1);
    $n2 = count($a2);
    $values = [];
    $mask = [];
    // Make first row and column of matrix
    for ($y=-1;$y<$n1;$y++) $matrix[$y][-1] = 0;
    for ($x=-1;$x<$n2;$x++) $matrix[-1][$x] = 0;
    // Fill the rest of matrix
    for ($y=0;$y<$n1;$y++) {
        for ($x=0;$x<$n2;$x++) {
            $matrix[$y][$x] = ($a1[$y] == $a2[$x]) ? ($matrix[$y-1][$x-1] + 1) : (max($matrix[$y-1][$x],$matrix[$y][$x-1]));
        }
    }
    // Evaluate each letter and mark it if it's different or the same
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
            if ($pmc) $result .= $closetag;
            if ($mc) $result .= $opentag;
        }
        $result .= $values[$k];
        $pmc = $mc;
    }
    if ($pmc) $result .= $closetag;
    return $result;
}
// Response to lettersize request (used in tool.py)
if (!empty($_GET['lettersize'])) {
    $text = urldecode($_GET['lettersize']);
    $array = ["from" => $text, "converted" => lettersize($text)];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($array);
    exit;
}
// Microplastics response to request in modal (whole and searched/filtered due to slow JS reaction)
if (isset($_GET['micro'])) {
    $echa520 = json_decode(file_get_contents("echa520.json",true));
    foreach ($echa520 as $ing) {
        if (str_contains(strtolower($ing),urldecode($_GET['micro']))) {
            echo '<li class="list-group-item user-select-all" ondblclick=(copyText(this.innerText))>' . lettersize($ing) . '</li>'; 
        }
    } 
    exit;
}
// Response to anx request
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
        if ($annex[1] == "all") { ?>
            <thead>
                <tr><?php foreach ($fileraw[0] as $cell) echo '<th scope="col">' . $cell . '</th>'; ?></tr>
            </thead>
            <tbody class="table-group-divider"><?php
                foreach ($fileraw as $key => $row) {
                    if ($key == 0) continue;
                    echo '<tr>';
                    foreach ($row as $cell) echo '<td>'. $cell .'</td>';
                    echo '</tr>';
                } 
        } else {
            ?><thead>
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
        <?php }
        echo "</table>";
    }
    exit;
}
// Get INCI object if neccesary
if (!empty($_POST['inci']) || (!empty($_POST['inci-model']) && !empty($_POST['inci-compare'])) || isset($_GET['random']) || isset($_GET['details']) || isset($_GET['suggest']) || isset($_GET["query"])) {
    try {
        $inci = new INCI("INCI.json");
        $funcdict = json_decode(file_get_contents('functions.json'),true);
    } catch (Exception $e) {
        echo "Wystąpił błąd, odśwież stronę i spróbuj ponownie";
        exit;
    }
}
if (isset($_GET["query"])) {
    if (empty($_POST["ingredientsearch"])) {
        echo "<h3>Wpisz jaki składnik wyszukać...</h3>";
        exit;
    }
    $wheretolook = [];
    foreach (array_keys($_POST) as $post) {
        if ($post == "ingredientsearch") continue;
        $wheretolook[] = str_replace("check-","",$post);
    }
    if (empty($wheretolook)) {
        echo "<h3>Wybierz gdzie szukać składnika...</h3>";
        exit;
    }
    $found = $inci->search($_POST["ingredientsearch"],$wheretolook);
    if (empty($found)) {
        echo "<h3>Nic nie znaleziono</h3>";
        exit;
    } 
    foreach ($found as $f) {
        similar_text($f,strtoupper($_POST["ingredientsearch"]),$p);
        $ps[] = $p;
    }
    array_multisort($ps,SORT_DESC,$found);
    ?>
    <div class="table-responsive-md">
        <table class="table table-striped table-sm align-middle">
            <thead>
                <tr>
                    <th scope="col" class="col-6 word-break">INCI</th>
                    <th scope="col" class="col-2">Nr CAS</th>
                    <th scope="col" class="col-2">Nr WE <sup><span class="text-info" data-bs-toggle="tooltip" data-bs-title="Inne nazwy numeru WE: EC number / EINECS (2xx-xxx-x, 3xx-xxx-x) / ELINCS (4xx-xxx-x) / NLP (5xx-xxx-x)"><i class="bi bi-info-circle"></i></span></sup></th>
                    <th scope="col" class="col-1 text-center">1223/2009</th>
                    <?php if ($inci->extended): ?><th scope="col" class="text-center col-1">Szczegóły</th><?php endif; ?>
                    <?php if (!$inci->extended): ?><th scope="col" class="text-center col-1"; ?>">CosIng</th><?php endif; ?>
                </tr>
            </thead>
            <tbody class="table-group-divider">
            <?php
                foreach ($found as $ing) { ?>
                    <tr>
                        <th class="user-select-all" ondblclick="copyText(this.innerText)"><?php echo lettersize($inci->get($ing,"inci")); ?></th>
                        <td class="font-monospace"><?php foreach (explode(" / ",$inci->get($ing,"casNo")) as $cas) $cases[] = '<span class="user-select-all font-monospace nowrap" ondblclick="copyText(this.innerText)">' .$cas. '</span>'; echo implode(" / ",$cases); unset($cases); ?></td>
                        <td class="font-monospace"><?php foreach (explode(" / ",$inci->get($ing,"ecNo")) as $we) $wes[] = '<span class="user-select-all font-monospace nowrap" ondblclick="copyText(this.innerText)">' .$we. '</span>'; echo implode(" / ",$wes); unset($wes); ?></td>
                        <td class="text-center"><?php
                            if (str_contains($inci->get($ing,"anx"),"I/") || str_contains($inci->get($ing,"anx"),"V/")) {
                                if (str_contains($inci->get($ing,"anx"),'#')) {
                                    echo '<a href="#ingredientAnnex" class="text-reset" data-bs-toggle="modal">'. trim(substr($inci->get($ing,"anx"),0,strpos($inci->get($ing,"anx"),'#'))) .'</a> '. substr($inci->get($ing,"anx"),strpos($inci->get($ing,"anx"),'#'));
                                } else {
                                    echo '<a href="#ingredientAnnex" class="text-reset" data-bs-toggle="modal">'. $inci->get($ing,"anx") .'</a>';
                                }
                            } else {
                                echo $inci->get($ing,"anx"); 
                            }
                        ?></td>
                        <?php if ($inci->extended): ?><td class="text-center"><a class="text-reset link-underline link-underline-opacity-0" data-bs-toggle="modal" href="#details"><i class="bi bi-info-circle fs-5"></i></a></td>
                        <?php else: ?>
                        <td class="text-center"><?php if (!empty($inci->get($ing,"refNo"))) echo '<a class="text-reset link-underline link-underline-opacity-0" target="_blank" title="Link do składnika w CosIng" href="https://ec.europa.eu/growth/tools-databases/cosing/details/'.$inci->get($ing,"refNo").'"><i class="bi bi-info-circle"></i></a>';?></td>
                        <?php endif; ?>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>
    <?php
    exit;
}
// Response for ingredient details request
if (!empty($_GET['details'])) {
    $ingredientname = lettersize(urldecode($_GET['details']));
    $echa520 = json_decode(file_get_contents("echa520.json",true));
    echo '<div class="m-1">';
    echo '<h3 class="word-break">' . $ingredientname . '</h3>';
    echo '<hr class="border border-2 border-light">';
    echo '<div class="mx-2 mb-3">';
    if (!empty($inci->get($ingredientname,'description'))) {
        echo '<h4>Opis</h4>';
        echo '<p>' . $inci->get($ingredientname,'description')['pl'] . '</p>';
        echo '<hr>';
    }
    if ($inci->isprop('gif') && $inci->get($ingredientname,'gif')) {
        echo '<h4>Wzór chemiczny</h4>';
        echo '<div class="bg-white text-center p-4 rounded-3"><img src="img/'.$inci->get($ingredientname,"refNo").'.gif"></div>';
        echo '<hr>';
    }
    if (!empty($inci->get($ingredientname,'sccs'))) {
        echo '<h4>Opinie SCCS</h4>';
        foreach ($inci->get($ingredientname,'sccs') as $opinion) {
            echo '<p><a target="_blank" href="'.$opinion['url'].'">'.$opinion['name'].'</a></p>';
        }
        echo '<hr>';
    }
    ?>
    <h4>Funkcje składnika</h4>
    <ul><?php
        if (!empty($inci->get($ingredientname,"function"))) {
            foreach ($inci->get($ingredientname,"function") as $function) {
                echo "<li>" . $funcdict[$function]['pl'] . "</li>";
            }
        } else {
            echo "<li>" . $funcdict['UNKNOWN']['pl'] . "</li>";
        }
    ?></ul>
    <hr>
    <div class="row row-cols-2 g-3">
        <div class="col-auto d-inline-flex align-items-center"><h4 class="m-0">Mikroplastik wg ECHA-520 SCENARIO</h4></div>
        <div class="col-3 d-inline-flex align-items-center"><?php
            if (in_array(strtoupper($ingredientname),$echa520)) {
                echo '<i class="bi bi-check text-success fw-bold fs-1" data-bs-toggle="tooltip" data-bs-title="Jest to mikroplastik"></i>';
            } else {
                echo '<i class="bi bi-x text-danger fw-bold fs-1" data-bs-toggle="tooltip" data-bs-title="Nie jest to mikroplastik"></i>';
            }
        ?>
    </div></div>
    <hr>
    <h4>Linki do wyszukania składnika</h4>
    <div class="mt-3 mx-1 row gx-3 gy-2 row-cols-1 row-cols-md-2 row-cols-lg-4">
        <div class="col">
            <a class="btn btn-outline-danger w-100" target="_blank" href="https://www.ulprospector.com/en/eu/PersonalCare/search?incival=<?php echo urlencode(strtolower($ingredientname)); ?>"><img src="img/ulprospector.png" alt="Logo ulProspector" class="logo">ulProspector</a>
        </div>
        <div class="col">
            <a class="btn btn-outline-info w-100" target="_blank" href="<?php echo ($inci->isprop("cosmile") && $inci->get($ingredientname,"cosmile") != null) ? "https://cosmileeurope.eu/pl/inci/szczegoly/" . $inci->get($ingredientname,"cosmile") : "https://cosmileeurope.eu/pl/inci/skladnik/?q=" . urlencode(strtolower($ingredientname)); ?>"><img src="img/cosmile.png" alt="Logo Cosmile" class="logo">COSMILE</a>
        </div>
        <div class="col">
            <a class="btn btn-outline-primary w-100" target="_blank" href="https://ec.europa.eu/growth/tools-databases/cosing/details/<?php echo $inci->get($ingredientname,"refNo"); ?>"><img src="img/cosing.png" alt="Logo UE" class="logo">CosIng</a>
        </div>
        <div class="col">
            <a class="btn btn-outline-light w-100" target="_blank" href="https://www.google.pl/search?q=<?php echo urlencode(strtolower($ingredientname)); ?>"><img src="img/google.png" alt="Logo Google" class="logo">Google</a>
        </div>
    </div>
    </div>
    </div>
    <?php
    exit;
}
// Response for suggest request
if (!empty($_GET['suggest'])) {
    $percent = (!empty($_GET['percent']) && intval($_GET['percent'])) ? $_GET['percent'] : 90;
    $suggestions = $inci->suggest(urldecode($_GET['suggest']),$percent,$endpercent);
    $array = [
        "query" => urldecode($_GET['suggest']),
        "requested_percent" => $_GET['percent'],
        "get_percent" => $endpercent,
        "suggestions" => $suggestions
    ];
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($array);
    exit;
}
// Process INCI validation
if (!empty($_POST['inci'])) {
    // Get main separator from select or difsep input
    $mainseparator = ($_POST['separator'] == "difsep") ? " " . trim($_POST['difsep']) . " " : $_POST['separator'];
    // Set connector to space or separator
    $connector = isset($_POST['connector']) ? $mainseparator : " ";
    $inciexp = explode($mainseparator,str_replace(array("\r\n", "\n", "\r"),$connector,$_POST['inci']));
    foreach ($inciexp as $ingredient) {
        if (empty($ingredient)) continue;
        $incitest[] = (str_contains($ingredient,"(nano)") && !str_contains($ingredient," (nano)")) ? lettersize(trim(str_replace("(nano)"," (nano)",$ingredient))) : lettersize(trim($ingredient));
    }
    // Recreate ingredients with correct lettersize to show in textarea
    $recreate = implode($mainseparator,$incitest);
    // Check if there are mistakes in INCI
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
    // Set cookies
    setcookie("inci",$_POST["inci"]);
    setcookie("separator",$_POST["separator"]);
    setcookie("difsep",!empty($_POST["difsep"]) ? $_POST["difsep"] : "");
    setcookie("connector",isset($_POST["connector"]) ? true : false);
}
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
        $marked = diff($incimodel,$incicompare,'<span class="text-danger">','</span>');
    }
    // Get main separator from select or difsep input
    $mainseparator = ($_POST['separator'] == "difsep") ? " " . trim($_POST['difsep']) . " " : $_POST['separator'];
    // Exploded inci-model to analyze ingredients
    $inciexp = explode($mainseparator,$_POST['inci-model']);
    foreach ($inciexp as $ingredient) {
        if (empty($ingredient)) continue;
        $incitest[] = (str_contains($ingredient,"(nano)") && !str_contains($ingredient," (nano)")) ? lettersize(trim(str_replace("(nano)"," (nano)",$ingredient))) : lettersize(trim($ingredient));
    }
    $fail = 0;
    foreach ($incitest as $ingredient) { 
        // Cut-off nano part and check if ingredient is correct
        $temping = trim(str_replace(" (nano)","",$ingredient));
        if (!$inci->check($temping)) $fail = 1;
    }
    // Check for duplicates
    $counted = array_count_values(array_map('strtoupper',$incitest));
    foreach ($counted as $key => $value) {
        if ($value > 1) $duplicates[] = $key;
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
// Get exchange rates from today's NBP table A and put everything into cookies
$jsoneur = json_decode(file_get_contents("https://api.nbp.pl/api/exchangerates/rates/a/eur/?format=json"),true);
setcookie("exchange_eur",$jsoneur['rates'][0]['mid']);
$jsonusd = json_decode(file_get_contents("https://api.nbp.pl/api/exchangerates/rates/a/usd/?format=json"),true);
setcookie("exchange_usd",$jsonusd['rates'][0]['mid']);
setcookie("exchange_date",date("d.m.Y",strtotime($jsoneur['rates'][0]['effectiveDate'])));

// HTTP Headers
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
header("Content-Security-Policy: default-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net/npm/; img-src 'self' data:");
header("X-Frame-Options: SAMEORIGIN");
header("X-Content-Type-Options: nosniff");

// Files versions - auto get from first line
$css_ver = date("yWNHis" ,filemtime("styles.css"));
$js_ver = date("yWNHis" ,filemtime("script.js"));
?>
<!DOCTYPE HTML>
<html lang="pl" data-bs-theme="dark">
<head>   
    <title>Sprawdzanie INCI | <?php echo $_SERVER['HTTP_HOST']; ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="Mikołaj Piętka">
    <meta name="description" content="Aplikacja do weryfikacji poprawności składu kosmetyku oraz podsumowanie informacji o składnikach (zgodnie z Rozporządzeniem 1223/2009)">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Page assets -->
    <link href="styles.css?v=<?php echo $css_ver; ?>" rel="stylesheet">
    <script src="script.js?v=<?php echo $js_ver; ?>" defer></script>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
</head>
<body class="bg-dark">
    <nav class="container my-3">
        <div class="navbar navbar-expand-lg bg-body-tertiary border rounded-3 px-3 py-1">
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbar">
                <div class="navbar-nav nav-underline">
                    <a href="index.php" class="nav-link<?php if (empty($_GET)) echo " active"; ?>">Weryfikacja</a>
                    <a href="?compare" class="nav-link<?php if (isset($_GET['compare'])) echo " active"; ?>">Porównanie</a>
                    <a href="?search" class="nav-link<?php if (isset($_GET['search'])) echo " active"; ?>">Szukaj</a>
                    <a href="#wholeAnnex" data-bs-toggle="modal" class="nav-link">Załączniki</a>
                    <a href="#info" data-bs-toggle="modal" class="nav-link">Informacje</a>
                    <a href="#microplastics" data-bs-toggle="modal" class="nav-link">ECHA-520</a>
                    <a href="#currency" data-bs-toggle="modal" class="nav-link">Kursy walut</a>
                    <a href="https://ec.europa.eu/growth/tools-databases/cosing/" target="_blank" class="nav-link">CosIng<i class="ms-2 bi bi-box-arrow-up-right"></i></a>
                </div>
            </div>
        </div>
    </nav>
    <?php if (!isset($_GET['search'])): ?>
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
                <div class="col px-4">
                    <button type="submit" class="btn btn-outline-light w-100" id="submit"><i class="bi bi-check2-square"></i> Sprawdź</button>
                </div>
                <div class="col px-4">
                    <button type="button" class="btn btn-outline-danger w-100" onclick="cleartextarea()"><i class="bi bi-trash3-fill"></i> Wyczyść</button>
                </div>
                <div class="col px-4">
                    <button type="button" class="btn btn-outline-success w-100<?php if (empty($_POST['inci']) || isset($_GET['compare'])) echo " disabled"; ?>" onclick="ctrlz()"><i class="bi bi-arrow-counterclockwise"></i> Cofnij zmiany</button>
                </div>
                <div class="btn-group col px-4" role="group">
                    <input type="checkbox" class="btn-check" name="connector" id="connector" <?php if (isset($_POST['connector'])) echo "checked"; if (isset($_GET['compare'])) echo "disabled"; ?>>
                    <label class="btn btn-outline-primary" for="connector">Zamień <i class="bi bi-arrow-return-left"></i> na separator</label>
                </div>
                <div class="col px-4">
                    <select class="form-select" name="separator" id="separator">
                        <option value=", " <?php if ((isset($_POST['separator']) && $_POST['separator'] == ", ") || !isset($_POST['separator'])) echo "selected"; ?>>Separator: ","</option>
                        <option value=" • " <?php if (isset($_POST['separator']) && $_POST['separator'] == " • ") echo "selected"; ?>>Separator: "•"</option>
                        <option value=" (and) " <?php if (isset($_POST['separator']) && $_POST['separator'] == " (and) ") echo "selected"; ?>>Separator: "(and)"</option>
                        <option value="difsep" <?php if (isset($_POST['separator']) && $_POST['separator'] == "difsep") echo "selected"; ?>>Inny</option>
                    </select>
                </div>
                <div class="col px-4">
                    <input type="text" class="form-control" name="difsep" id="difsep" placeholder="Inny separator" <?php if (!empty($_POST['difsep']) && (isset($_POST['separator']) && $_POST['separator'] == "difsep")) echo 'value="' . $_POST['difsep'] . '"'; if (!(isset($_POST['separator']) && $_POST['separator'] == "difsep")) echo " disabled" ?>>
                </div>
            </div>
        </form>
    </div>
    <div class="container-fluid ingredients">
        <div class="mx-2 mx-lg-4 mt-2">
            <?php if (isset($incitest)): 
            if ($fail): ?>
                <h3 class="text-danger fw-bold">Błędne INCI <i class="bi bi-emoji-frown-fill"></i></h3>
            <?php elseif (empty($duplicates)):?>
                <h3 class="text-success fw-bold">Poprawne INCI <i class="bi bi-hand-thumbs-up-fill"></i></h3>
            <?php else: ?>
                <h3 class="text-warning fw-bold">INCI zawiera powtórzenia <i class="bi bi-exclamation-triangle"></i></h3>
            <?php endif; 
            if (!empty($_POST['inci-model']) && !empty($_POST['inci-compare'])):  if ($comparison): ?>
                <h3 class="text-success fw-bold">Indentyczne składy <i class="bi bi-hand-thumbs-up-fill"></i></h3>
            <?php else: ?>
                <h3 class="text-danger fw-bold">Składy nie są Indentyczne <i class="bi bi-emoji-frown-fill"></i></h3>
                <div class="d-flex gap-2"><strong class="text-danger">Różnice:</strong><span><?php echo $marked; ?></span></div>
            <?php endif; endif; 
            if (isset($_POST['inci'])): ?>
            <div class="card d-inline-block my-2">
                <div class="card-body">
                    <div class="d-inline-flex flex-wrap gap-3">
                        <div class="d-inline-flex gap-3 align-items-center">
                            <i class="bi bi-tools fs-4"></i>
                            <div class="d-inline-flex flex-wrap gap-3">
                                <?php if (!$fail): ?>
                                <button type="button" class="btn btn-sm btn-outline-light" onclick="downloadTable()"><i class="bi bi-download"></i> Pobierz tabelę</button>
                                <button type="button" class="btn btn-sm btn-outline-light" onclick="copyText(document.querySelector('#inci').value)"><i class="bi bi-copy"></i> Kopiuj skład</button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-outline-light" onclick="pasteinci()"><i class="bi bi-clipboard2-fill"></i> Wklej ze schowka</button>
                            </div>
                        </div>
                        <div class="d-inline-flex gap-3 align-items-center">
                            <i class="bi bi-info-circle fs-4"></i>
                            <div class="card d-inline-block border-light px-2 py-1 lh-sm rounded-1">
                                <span class="font-sm text-light"><i class="bi bi-question-circle"></i> Ilość składników: <?php echo count($incitest); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle caption-top">
                <caption><?php if ($fail && !isset($_GET["compare"])) echo "Podwójne kliknięcia na podpowiedź powoduje zamianę błędnego składnika na zaznaczony."; else echo "Podwójne kliknięcie na tekst kopiuje go do schowka."; ?></caption>
                <thead>
                    <tr>
                        <th scope="col" class="dwn">INCI</th>
                        <?php if ($fail): ?>
                        <th scope="col" class="col-9">Podpowiedzi składników</th>
                        <th scope="col" class="text-center col-1">więcej...</th>
                        <?php else: ?>
                        <th scope="col" class="dwn col-2">Nr CAS</th>
                        <th scope="col" class="dwn col-2">Nr WE <sup><span class="text-info" data-bs-toggle="tooltip" data-bs-title="Inne nazwy numeru WE: EC number / EINECS (2xx-xxx-x, 3xx-xxx-x) / ELINCS (4xx-xxx-x) / NLP (5xx-xxx-x)"><i class="bi bi-info-circle"></i></span></sup></th>
                        <th scope="col" class="text-center col-1">1223/2009</th>
                        <th scope="col" class="dwn col-2">Funkcja</th>
                        <th scope="col" class="dwn d-none">Function</th>
                        <?php if ($inci->extended): ?><th scope="col" class="text-center col-1">Szczegóły</th><?php endif; ?>
                        <?php if (!$inci->extended): ?><th scope="col" class="text-center col-1"; ?>">CosIng</th><?php endif; ?>
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
                            $suggestionsraw = $inci->suggest($temping,endpercent:$perc);
                            if ($suggestionsraw != null) {
                                $sugred = [];
                                foreach ($suggestionsraw as $s) {
                                    $sugred[] = (isset($_GET['compare'])) ? lettersize($s["inci"]) : '<span class="user-select-all nowrap" data-bs-toggle="tooltip" data-bs-title="Podobieństwo: '.$s["similarity"].'%" ondblclick="correctmistake(this)">' . lettersize($s["inci"]) . '</span>';
                                }
                                $suggestions = implode($mainseparator,$sugred).'<i class="d-none percent">'.$perc.'</i>';
                            } else {
                                $suggestions = '<span class="fst-italic">Brak podpowiedzi w tym zakresie, kliknij "Pokaż więcej" żeby zwiększyć zakres</span><i class="d-none percent">'.$perc.'</i>';
                            }
                        }
                    ?>
                        <tr>
                            <th scope="row"  class="dwn<?php if (!$test) echo ' text-danger'; if ($test && !empty($duplicates) && in_array(strtoupper($ingredient),$duplicates)) echo ' text-warning'; ?>"><span class="user-select-all" ondblclick="copyText(this.innerText)"><?php echo lettersize($ingredient); ?></span></th>
                            <?php if ($fail): ?>
                            <td class="font-sm"><?php if (!$test) echo $suggestions; ?></td>
                            <td class="text-center"><?php if (!$test): ?><button type="button" class="btn btn-tiny btn-outline-light" onclick="getsuggestions(this)">Pokaż więcej</button><?php endif; ?></td>
                            <?php else: ?>
                            <td class="dwn"><?php foreach (explode(" / ",$inci->get($temping,"casNo")) as $cas) $cases[] = '<span class="user-select-all font-monospace nowrap" ondblclick="copyText(this.innerText)">' .$cas. '</span>'; echo implode(" / ",$cases); unset($cases); ?></td>
                            <td class="dwn"><?php foreach (explode(" / ",$inci->get($temping,"ecNo")) as $we) $wes[] = '<span class="user-select-all font-monospace nowrap" ondblclick="copyText(this.innerText)">' .$we. '</span>'; echo implode(" / ",$wes); unset($wes); ?></td>
                            <td class="text-center"><?php 
                                if (str_contains($inci->get($temping,"anx"),"I/") || str_contains($inci->get($temping,"anx"),"V/")) {
                                    if (str_contains($inci->get($temping,"anx"),'#')) {
                                        echo '<a href="#ingredientAnnex" class="text-reset" data-bs-toggle="modal">'. trim(substr($inci->get($temping,"anx"),0,strpos($inci->get($temping,"anx"),'#'))) .'</a> '. substr($inci->get($temping,"anx"),strpos($inci->get($temping,"anx"),'#'));
                                    } else {
                                        echo '<a href="#ingredientAnnex" class="text-reset" data-bs-toggle="modal">'. $inci->get($temping,"anx") .'</a>';
                                    }
                                } else {
                                    echo $inci->get($temping,"anx"); 
                                }
                            ?></td>
                            <td class="dwn"><?php
                                // Functions in polish
                                foreach ($inci->get($temping,"function") as $function) {
                                    $ingfunc[] = $funcdict[$function]['pl']; 
                                }
                                echo (!empty($ingfunc)) ? implode(", ",array_map(function ($txt) {return '<span class="user-select-all" ondblclick="copyText(this.innerText)">' . $txt . '</span>'; },$ingfunc)) : '<span class="user-select-all" ondblclick="copyText(this.innerText)">' . $funcdict['UNKNOWN']['pl'] . '</span>';
                                unset($ingfunc);                                      
                            ?></td>
                            <td class="dwn d-none"><?php
                                // Functions in english
                                foreach ($inci->get($temping,"function") as $function) {
                                    $ingfunc[] = $funcdict[$function]['en']; 
                                }
                                echo (!empty($ingfunc)) ? implode(", ",$ingfunc) : $funcdict['UNKNOWN']['en']; 
                                unset($ingfunc);
                            ?></td>
                            <?php if ($inci->extended): ?><td class="text-center"><a class="text-reset link-underline link-underline-opacity-0" data-bs-toggle="modal" href="#details"><i class="bi bi-info-circle fs-5"></i></a></td><?php endif; ?>
                            <?php if (!$inci->extended): ?><td class="text-center"><?php if (!empty($inci->get($temping,"refNo"))) echo '<a class="text-reset link-underline link-underline-opacity-0" target="_blank" title="Link do składnika w CosIng" href="https://ec.europa.eu/growth/tools-databases/cosing/details/'.$inci->get($temping,"refNo").'"><i class="bi bi-info-circle"></i></a>';?></td><?php endif; ?>
                            <?php endif; ?>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="container my-3" id="searchINCI">
        <div class="d-flex justify-content-center">
            <div class="card col-12 col-md-8 col-lg-5 m-4">
                <div class="card-body p-5">
                    <div class="text-center m-2">
                        <h3>Wyszukaj składnik</h3>
                    </div>
                    <form method="post">
                        <div class="mx-3">
                            <input type="search" class="form-control" id="ingredientsearch" name="ingredientsearch" autofocus required>
                        </div>
                        <div class="m-3">
                            <h5>Wyszukaj w:</h5>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="check-inci" name="check-inci" checked>
                                <label for="check-inci" class="form-check-label">INCI</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="check-casNo" name="check-casNo" checked>
                                <label for="check-casNo" class="form-check-label">Numery CAS</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="check-ecNo" name="check-ecNo">
                                <label for="check-ecNo" class="form-check-label">Numery WE</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="check-description" name="check-description">
                                <label for="check-description" class="form-check-label">Opisy składników</label>
                            </div>
                        </div>
                        <div class="text-center">
                            <button type="submit" class="btn btn-outline-primary w-50"><i class="bi bi-search"></i> Wyszukaj</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid my-3" id="search-response"></div>
    <?php endif; ?>
    <div id="modals">
        <div class="modal fade" id="ingredientAnnex" tabindex="-1" data-bs-backdrop="static">
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
                        <h2 class="modal-title">Szczegóły składnika</h2>
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
        <div class="modal fade" id="wholeAnnex" tabindex="-1" data-bs-backdrop="static">
            <div class="modal-dialog modal-fullscreen modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="d-flex gap-3 w-75">
                            <h4 class="modal-title w-50">Załączniki</h4>
                            <select class="form-select" name="wholeAnnex">
                                <option value="0" selected>Wybierz...</option>
                                <option value="II/all">Załącznik II</option>
                                <option value="III/all">Załącznik III</option>
                                <option value="IV/all">Załącznik IV</option>
                                <option value="V/all">Załącznik V</option>
                                <option value="VI/all">Załącznik VI</option>
                            </select>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
                                <input type="number" class="form-control" id="eur" step="0.01">
                            </div>
                            <div class="col">
                                <label for="plneur">PLN</label>
                                <input type="number" class="form-control" id="plneur" step="0.01">
                            </div>
                        </div>
                        <h4>Dolar $</h4>
                        <div class="row g-3 row-cols-1 row-cols-lg-2 mb-4">
                            <div class="col">
                                <label for="usd">USD</label>
                                <input type="number" class="form-control" id="usd" step="0.01">
                            </div>
                            <div class="col">
                                <label for="plnusd">PLN</label>
                                <input type="number" class="form-control" id="plnusd" step="0.01">
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
                        <p>Celem aplikacji jest weryfikacja składu zgodnie ze słownikiem wspólnych nazw składników kosmetycznych zgodnie z DECYZJĄ WYKONAWCZĄ KOMISJI (UE) 2022/677 z dnia 31 marca 2022 roku. <a href="https://eur-lex.europa.eu/legal-content/pl/TXT/?uri=CELEX%3A32022D0677" target="_blank"><i class="bi bi-box-arrow-up-right"></i></a><br>Dodatkowo aplikacja umożliwia rozpisanie i sprawdzenie wszystkich składników oraz wyszukanie szczegółów zawartych w bazie CosIng oraz załącznikach ROZPORZĄDZENIA (UE) 1223/2009. <a href="https://eur-lex.europa.eu/eli/reg/2009/1223" target="_blank"><i class="bi bi-box-arrow-up-right"></i></a></p>
                        <p>Na stronie działają skróty klawiszowe: <br> <kbd>Ctrl + <i class="bi bi-arrow-return-left"></i></kbd> - skrót do przycisku Sprawdź <br> <kbd>Ctrl + Del</kbd> - skrót do przycisku Wyczyść</p>
                        <h3>Aktualizacje plików</h3>
                        <table class="table">
                            <tr>
                                <th scope="row">Aktualizacja bazy składników</th>
                                <td><?php
                                    $csvmod = file_exists('INCI.csv') ? filemtime('INCI.csv') : 0;
                                    $jsnmod = file_exists('INCI.json') ? filemtime('INCI.json') : 0;
                                    echo date("d.m.Y H:i", max($csvmod,$jsnmod)); 
                                ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Aktualizacja załącznika II</th>
                                <td><?php echo file_exists('A2.csv') ? date("d.m.Y H:i", filemtime('A2.csv')) : "Błąd odczytu pliku!"; ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Aktualizacja załącznika III</th>
                                <td><?php echo file_exists('A3.csv') ? date("d.m.Y H:i", filemtime('A3.csv')) : "Błąd odczytu pliku!"; ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Aktualizacja załącznika IV</th>
                                <td><?php echo file_exists('A4.csv') ? date("d.m.Y H:i", filemtime('A4.csv')) : "Błąd odczytu pliku!"; ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Aktualizacja załącznika V</th>
                                <td><?php echo file_exists('A5.csv') ? date("d.m.Y H:i", filemtime('A5.csv')) : "Błąd odczytu pliku!"; ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Aktualizacja załącznika VI</th>
                                <td><?php echo file_exists('A6.csv') ? date("d.m.Y H:i", filemtime('A6.csv')) : "Błąd odczytu pliku!"; ?></td>
                            </tr>
                        </table>
                        <div class="text-center"><i class="bi bi-c-circle"></i> <a href="mailto:mikolaj.pietka98@gmail.com" class="text-reset link-underline link-underline-opacity-0">Mikołaj Piętka</a></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal fade" id="tools" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4 class="modal-title">Narzędzia</h4>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <h6>to-uppercase</h6>
                        <input type="search" id="toupper" class="form-control" placeholder="Tekst do zamiany na wielkie litery">
                        <div class="d-flex gap-2 align-items-center my-2"><button type="button" class="btn btn-outline-light btn-sm" onclick="copyText(document.querySelector('#out-toupper').innerText)"><i class="bi bi-clipboard2-plus-fill"></i></button><i class="bi bi-chevron-right"></i><span id="out-toupper" class="text-break"></span></div>
                        <h6>INCI lettersize</h6>
                        <input type="search" id="lettersize" class="form-control" placeholder="Wprowadź nazwę INCI">
                        <div class="d-flex gap-2 align-items-center my-2"><button type="button" class="btn btn-outline-light btn-sm" onclick="copyText(document.querySelector('#out-lettersize').innerText)"><i class="bi bi-clipboard2-plus-fill"></i></button><i class="bi bi-chevron-right"></i><span id="out-lettersize" class="text-break"></span></div>
                        <h6>strlen</h6>
                        <input type="search" id="strlen" class="form-control" placeholder="Wprowadź tekst żeby uzyskać jego długość">
                        <div class="d-flex gap-2 align-items-center my-2"><div class="card rounded-1 d-inline-block border-light px-2 py-1 lh-sm"><span class="font-sm text-light"><i class="bi bi-calculator"></i></span></div></button><i class="bi bi-chevron-right"></i><span id="out-strlen" class="text-break"></span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="position-fixed start-50 translate-middle-x top-0 mt-5 z-up" id="toast">
        <div class="toast fade text-bg-light" role="alert" data-bs-delay="1200">
            <div class="toast-body fw-bold fs-6 text-center">
                <p class="mb-1"></p>
                <span class="fst-italic font-sm lh-1"></span>
            </div>
        </div>
    </div>
</body>
</html>