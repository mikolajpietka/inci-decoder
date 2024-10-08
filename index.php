<?php 
setlocale(LC_ALL,'pl_PL');
date_default_timezone_set('Europe/Warsaw');
error_reporting(0);
$pagetitle = "Sprawdzanie INCI";

function lettersize($text,$additional_separator=null,$debug=false) {
    $rp = json_decode(file_get_contents("replacetable.json"),true);
    $text = strtolower($text);
    $separators = [",",".","-","+","(",")"," ","/","&",":","'"];
    // If additional separator in provided
    if ($additional_separator != null) {
        $processeps = str_split($additional_separator);
        foreach ($processeps as $processsep) {
            if (!in_array($processsep,$separators)) {
                $separators[] = $processsep;
            }
        }
    }
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
    // Make uppercase when needed
    foreach ($split as $part) {
        $partlen = strlen($part);
        if (array_key_exists($partlen,$rp) && array_key_exists($part,$rp[$partlen])) {
            $newpart[] = strtr($part,$rp[$partlen]);
        } else {
            $newpart[] = ucfirst($part);
        }
    }
    // Debug output
    if ($debug) {
        echo "Separators to check: " . implode(" | ",$separators) . "<br>";
        if (!empty($usedseps)) echo "Used separators in text: " . implode(" | ",$usedseps); else echo "No separators";
        echo "<br>";
        if (!empty($positions)) echo "Sorted positions of separators: " . implode(" | ",$positions) . "<br>";
        echo "Splitted text: " . implode(" | ",$split) . "<br>";
        echo "Splitted corrected text: " . implode(" | ",$newpart) . "<br>";
        echo "Corrected text: " . implode($newpart);
    }
    // Return corrected 
    return implode($newpart);
}

if (isset($_GET['test']) && isset($_POST['inci'])) {
    echo lettersize($_POST['inci'],null,true);
}

function wyszukajpodpowiedz($text,$array) {
    $text = strtoupper($text);
    $raw = array_filter($array, function($v,$k) use ($text) {
        if (str_starts_with($v,substr($text,0))) {
            return $v;
        }
    }, ARRAY_FILTER_USE_BOTH);
    foreach ($raw as $inci) {
        $podpowiedz[] = '<span class="user-select-all" ondblclick="copyInci(this)">' . lettersize($inci) . '</span>';
    }
    if (!isset($podpowiedz)) {
        $answer = null;
    } else {
        $answer = implode(', ',$podpowiedz);
    }
    if ($answer == null) {
        return wyszukajpodpowiedz(substr($text,0,-2),$array);
    } else {
        return $answer;
    }
}

if (isset($_GET['anx'])) {
    $request = urldecode($_GET['anx']);

    if (str_contains($request,',')) {
        $annexes = explode(', ',$request);
    } else {
        $annexes[] = $request;
    }
    foreach ($annexes as $anx) {
        $annex = explode('/',$anx);
        switch ($annex[0]) {
            case 'II':
                if (file_exists('A2.csv') && !empty($fileraw = array_map('str_getcsv', file('A2.csv')))) {
                    if ($annex[1] == 'all') {
                        ?>
                        <h3>Załącznik II: Wykaz substancji zakazanych w produktach kosmetycznych</h3>
                        <table class="table">
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
                            </tbody>
                        </table>
                        <?php
                    } else {
                        foreach ($fileraw as $row) {
                            $file[$row[0]] = [
                                'substance' => $row[1],
                                'CAS' => $row[2],
                                'WE' => $row[3]
                            ];
                        } ?>
                        <div class="mb-5">
                            <h3>Załącznik II: Wykaz substancji zakazanych w produktach kosmetycznych</h3>
                            <table class="table mt-3">
                                <thead>
                                    <tr>
                                        <th scope="col" class="col-4">Kolumna</th>
                                        <th scope="col" class="col-8">Treść</th>
                                    </tr>
                                </thead>
                                <tbody class="table-group-divider">
                                    <tr>
                                        <th scope="row">Numer porządkowy</th>
                                        <td><?php echo $annex[1]; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Nazwa chemiczna / INN</th>
                                        <td><?php echo $file[$annex[1]]['substance']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">nr CAS</th>
                                        <td class="font-monospace"><?php echo $file[$annex[1]]['CAS']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">nr WE</th>
                                        <td class="font-monospace"><?php echo $file[$annex[1]]['WE']; ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <?php
                    }
                } else {
                    echo 'Błąd odczytu pliku! Odśwież stronę i spróbuj ponownie';
                    exit;
                }
                break;
            case 'III':
                if (file_exists('A3.csv') && !empty($fileraw = array_map('str_getcsv', file('A3.csv')))) {
                    if ($annex[1] == 'all') {
                        ?>
                        <h3>Załącznik III: Wykaz substancji, które mogą być zawarte w produktach kosmetycznych wyłącznie z zastrzeżeniem określonych ograniczeń</h3>
                        <table class="table">
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
                            </tbody>
                        </table>
                        <?php
                    } else {
                        foreach ($fileraw as $row) {
                            $file[$row[0]] = [
                                'inn' => $row[1],
                                'inci' => $row[2],
                                'cas' => $row[3],
                                'we' => $row[4],
                                'type' => $row[5],
                                'max' => $row[6],
                                'other' => $row[7],
                                'conditions' => $row[8]
                            ];
                        } ?>
                        <div class="mb-5">
                            <h3>Załącznik III: Wykaz substancji, które mogą być zawarte w produktach kosmetycznych wyłącznie z zastrzeżeniem określonych ograniczeń</h3>
                            <table class="table mt-3">
                                <thead>
                                    <tr>
                                        <th scope="col" class="col-4">Kolumna</th>
                                        <th scope="col" class="col-8">Treść</th>
                                    </tr>
                                </thead>
                                <tbody class="table-group-divider">
                                    <tr>
                                        <th scope="row">Numer porządkowy (a)</th>
                                        <td><?php echo $annex[1]; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Nazwa chemiczna / INN (b)</th>
                                        <td><?php echo $file[$annex[1]]['inn']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Nazwa w słowniku wspólnych nazw / INCI (c)</th>
                                        <td><?php echo $file[$annex[1]]['inci']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">nr CAS (d)</th>
                                        <td class="font-monospace"><?php echo $file[$annex[1]]['cas']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">nr WE (e)</th>
                                        <td class="font-monospace"><?php echo $file[$annex[1]]['we']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Rodzaj produktu, części ciała (f)</th>
                                        <td><?php echo $file[$annex[1]]['type']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Maksymalne stężenie w preparacie gotowym do użycia (g)</th>
                                        <td><?php echo $file[$annex[1]]['max']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Inne (h)</th>
                                        <td><?php echo $file[$annex[1]]['other']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Warunki i ostrzeżenia na opakowaniach (i)</th>
                                        <td><?php echo $file[$annex[1]]['conditions']; ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <?php
                    }
                } else {
                    echo 'Błąd odczytu pliku! Odśwież stronę i spróbuj ponownie';
                    exit;
                }
                break;
            case 'IV':
                if (file_exists('A4.csv') && !empty($fileraw = array_map('str_getcsv', file('A4.csv')))) {
                    if ($annex[1] == 'all') {
                        ?>
                        <h3>Załącznik IV: Wykaz barwników dopuszczonych w produktach kosmetycznych</h3>
                        <table class="table">
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
                            </tbody>
                        </table>
                        <?php
                    } else {
                        foreach ($fileraw as $row) {
                            $file[$row[0]] = [
                                'name' => $row[1],
                                'ci' => $row[2],
                                'cas' => $row[3],
                                'we' => $row[4],
                                'colour' => $row[5],
                                'type' => $row[6],
                                'max' => $row[7],
                                'other' => $row[8],
                                'conditions' => $row[9]
                            ];
                        } ?>
                        <div class="mb-5">
                            <h3>Załącznik IV: Wykaz barwników dopuszczonych w produktach kosmetycznych</h3>
                            <table class="table mt-3">
                                <thead>
                                    <tr>
                                        <th scope="col" class="col-4">Kolumna</th>
                                        <th scope="col" class="col-8">Treść</th>
                                    </tr>
                                </thead>
                                <tbody class="table-group-divider">
                                    <tr>
                                        <th scope="row">Numer porządkowy (a)</th>
                                        <td><?php echo $annex[1]; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Nazwa chemiczna (b)</th>
                                        <td><?php echo $file[$annex[1]]['name']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Numer/nazwa wg wykazu barwników zawartego w słowniku (c)</th>
                                        <td><?php echo $file[$annex[1]]['ci']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">nr CAS (d)</th>
                                        <td class="font-monospace"><?php echo $file[$annex[1]]['cas']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">nr WE (e)</th>
                                        <td class="font-monospace"><?php echo $file[$annex[1]]['we']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Kolor (f)</th>
                                        <td><?php echo $file[$annex[1]]['colour']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Rodzaj produktu, części ciała (g)</th>
                                        <td><?php echo $file[$annex[1]]['type']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Maksymalne stężenie w preparacie gotowym do użycia (h)</th>
                                        <td><?php echo $file[$annex[1]]['max']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Inne (i)</th>
                                        <td><?php echo $file[$annex[1]]['other']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Brzmienie warunków stosowania i ostrzeżeń (j)</th>
                                        <td><?php echo $file[$annex[1]]['conditions']; ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <?php
                    }
                } else {
                    echo 'Błąd odczytu pliku! Odśwież stronę i spróbuj ponownie';
                    exit;
                }
                break;
            case 'V':
                if (file_exists('A5.csv') && !empty($fileraw = array_map('str_getcsv', file('A5.csv')))) {
                    if ($annex[1] == 'all') {
                        ?>
                        <h3>Załącznik V: Wykaz substancji konserwujących dozwolonych w produktach kosmetycznych</h3>
                        <table class="table">
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
                            </tbody>
                        </table>
                        <?php
                    } else {
                        foreach ($fileraw as $row) {
                            $file[$row[0]] = [
                                'name' => $row[1],
                                'inci' => $row[2],
                                'cas' => $row[3],
                                'we' => $row[4],
                                'type' => $row[5],
                                'max' => $row[6],
                                'other' => $row[7],
                                'conditions' => $row[8]
                            ];
                        } ?>
                        <div class="mb-5">
                            <h3>Załącznik V: Wykaz substancji konserwujących dozwolonych w produktach kosmetycznych</h3>
                            <table class="table mt-3">
                                <thead>
                                    <tr>
                                        <th scope="col" class="col-4">Kolumna</th>
                                        <th scope="col" class="col-8">Treść</th>
                                    </tr>
                                </thead>
                                <tbody class="table-group-divider">
                                    <tr>
                                        <th scope="row">Numer porządkowy (a)</th>
                                        <td><?php echo $annex[1]; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Nazwa chemiczna/INN (b)</th>
                                        <td><?php echo $file[$annex[1]]['name']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Nazwa w glosariuszu wspólnych nazw składników (c)</th>
                                        <td><?php echo $file[$annex[1]]['inci']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">nr CAS (d)</th>
                                        <td class="font-monospace"><?php echo $file[$annex[1]]['cas']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">nr WE (e)</th>
                                        <td class="font-monospace"><?php echo $file[$annex[1]]['we']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Rodzaj produktu, części ciała (f)</th>
                                        <td><?php echo $file[$annex[1]]['type']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Maksymalne stężenie w preparacie gotowym do użycia (g)</th>
                                        <td><?php echo $file[$annex[1]]['max']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Inne (h)</th>
                                        <td><?php echo $file[$annex[1]]['other']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Określenie warunków stosowania i ostrzeżeń (i)</th>
                                        <td><?php echo $file[$annex[1]]['conditions']; ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <?php
                    }
                } else {
                    echo 'Błąd odczytu pliku! Odśwież stronę i spróbuj ponownie';
                    exit;
                }
                break;
            case 'VI':
                if (file_exists('A6.csv') && !empty($fileraw = array_map('str_getcsv', file('A6.csv')))) {
                    if ($annex[1] == 'all') {
                        ?>
                        <h3>Załącznik VI: Wykaz substancji promieniochronnych dozwolonych w produktach kosmetycznych</h3>
                        <table class="table">
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
                            </tbody>
                        </table>
                        <?php
                    } else {
                        foreach ($fileraw as $row) {
                            $file[$row[0]] = [
                                'inn' => $row[1],
                                'inci' => $row[2],
                                'cas' => $row[3],
                                'we' => $row[4],
                                'type' => $row[5],
                                'max' => $row[6],
                                'other' => $row[7],
                                'conditions' => $row[8]
                            ];
                        } ?>
                        <div class="mb-5">
                            <h3>Załącznik VI: Wykaz substancji promieniochronnych dozwolonych w produktach kosmetycznych</h3>
                            <table class="table mt-3">
                                <thead>
                                    <tr>
                                        <th scope="col" class="col-4">Kolumna</th>
                                        <th scope="col" class="col-8">Treść</th>
                                    </tr>
                                </thead>
                                <tbody class="table-group-divider">
                                    <tr>
                                        <th scope="row">Numer porządkowy (a)</th>
                                        <td><?php echo $annex[1]; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Nazwa chemiczna / INN / XAN (b)</th>
                                        <td><?php echo $file[$annex[1]]['inn']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Nazwa w słowniku wspólnych nazw / INCI (c)</th>
                                        <td><?php echo $file[$annex[1]]['inci']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">nr CAS (d)</th>
                                        <td class="font-monospace"><?php echo $file[$annex[1]]['cas']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">nr WE (e)</th>
                                        <td class="font-monospace"><?php echo $file[$annex[1]]['we']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Rodzaj produktu, części ciała (f)</th>
                                        <td><?php echo $file[$annex[1]]['type']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Maksymalne stężenie w preparacie gotowym do użycia (g)</th>
                                        <td><?php echo $file[$annex[1]]['max']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Inne (h)</th>
                                        <td><?php echo $file[$annex[1]]['other']; ?></td>
                                    </tr>
                                    <tr>
                                        <th scope="row">Określenie warunków stosowania i ostrzeżeń (i)</th>
                                        <td><?php echo $file[$annex[1]]['conditions']; ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <?php
                    }
                } else {
                    echo 'Błąd odczytu pliku! Odśwież stronę i spróbuj ponownie';
                    exit;
                }
                break;
        }
    }
    exit;
}

$csv = array_map('str_getcsv', file('INCI.csv'));
foreach ($csv as $key => $ingredient) {
    if ($key == 0) continue;
    $ingredients[] = [
        'name' => $ingredient[1],
        'cas' => $ingredient[2],
        'we' => $ingredient[3],
        'annex' => $ingredient[4],
        'ref' => $ingredient[5],
        'function' => $ingredient[6]
    ];
}
$slownik = array_column($ingredients,'name');
$funcdict = json_decode(file_get_contents('functions.json'),true);

if (isset($_POST['whole'])) {
    if (!empty($_POST['inci'])) {
        // Different separators
        if ($_POST['separator'] == "difsep") {
            $mainseparator = $_POST['difsep'];
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
            $incitest[] = trim($ingredient);
        }
        $recreate = implode($mainseparator,$incitest);
        $fail = 0;
        foreach ($incitest as $ingredient) { 
            // Test for nano ingredients
            if (str_contains($ingredient,"(nano)")) {
                // If yes then cut-off nano part and check if ingredient is correct
                $temping = trim(str_replace("(nano)","",$ingredient));
                if (!in_array(strtoupper($temping),$slownik)) {
                    $fail = 1;
                }
            } else {
                // If no just check
                if (!in_array(strtoupper($ingredient),$slownik)) {
                    $fail = 1;
                }
            }
        }

        $counted = array_count_values(array_map('strtoupper',$incitest));
        foreach ($counted as $key => $value) {
            if ($value > 1) {
                $duplicates[] = $key;
            }
        }
    }
}
// Single ingredient search (paused part of project)
if (isset($_POST['single'])) {
    if (!empty($_POST['inci']) && empty($_POST['cas'])) {
        if (array_search(strtoupper($_POST['inci']),array_column($ingredients,'name')) !== false) {
            $searchedkeys[] = array_search(strtoupper($_POST['inci']),array_column($ingredients,'name'));
        } else {

        }
    } elseif (empty($_POST['inci']) && !empty($_POST['cas'])) {

    } else {

    }
}
// Showing random ingredient for testing
if (isset($_GET['random'])) {
    $incitest = array_rand(array_flip($slownik),1);
    if (is_string($incitest)) $incitest = array($incitest);
    $fail = false;
}
?>
<!DOCTYPE HTML>
<html lang="pl" data-bs-theme="dark">
<head>   
    <title><?php echo $pagetitle." | ".$_SERVER['HTTP_HOST']; ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="author" content="Mikołaj Piętka">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Material icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
    <!-- Page CSS -->
    <link href="styles.css?ver=2.3.inci" rel="stylesheet">
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
                <a href="index.php" class="nav-link<?php if (empty($_GET)) echo " active"; ?>">Cały skład</a>
                <a href="?single" class="nav-link visually-hidden disabled<?php if (isset($_GET['single'])) echo " active"; ?>">Pojedynczy składnik</a>
                <a href="#annex" data-bs-toggle="modal" class="nav-link">Podgląd załączników</a>
                <a href="#info" data-bs-toggle="modal" class="nav-link">Informacje</a>
                <a href="https://ec.europa.eu/growth/tools-databases/cosing/" target="_blank" class="nav-link">CosIng<i class="ms-2 bi bi-box-arrow-up-right"></i></a>
                <a href="?random" class="nav-link">Losowy składnik</a>
            </div>
        </div>
    </nav>
    <?php 
    if (!isset($_GET['single'])):
    ?>
    <div class="container my-3">
        <h2>Sprawdzanie INCI</h2>
        <h5>Weryfikacja poprawności składu ze słownikiem wspólnych nazw składników (INCI) <sup><span class="text-info" data-bs-toggle="tooltip" data-bs-title="Więcej szczegółów w odnośniku Informacje"><i class="bi bi-info-circle"></i></span></sup></h5>
        <form method="post">
            <textarea class="form-control" id="inci" name="inci" rows="12"><?php if (isset($recreate)) echo lettersize($recreate,$mainseparator); ?></textarea>
            <div class="d-flex gap-3 mt-3">
                <button type="submit" class="btn btn-outline-light w-20" name="whole">Sprawdź</button>
                <div class="btn-group w-20" role="group">
                    <input type="checkbox" class="btn-check" name="connector" id="connector">
                    <label class="btn btn-outline-primary" for="connector">Zamień <i class="bi bi-arrow-return-left"></i> na separator</label>
                </div>
                <select class="form-select w-20" name="separator" id="separator">
                    <option value=", " <?php if ((isset($_POST['separator']) && $_POST['separator'] == ", ") || !isset($_POST['separator'])) echo "selected"; ?>>Separator: ", "</option>
                    <option value=" • " <?php if (isset($_POST['separator']) && $_POST['separator'] == " • ") echo "selected"; ?>>"•"</option>
                    <option value="; " <?php if (isset($_POST['separator']) && $_POST['separator'] == "; ") echo "selected"; ?>>"; "</option>
                    <option value="difsep" <?php if (isset($_POST['separator']) && $_POST['separator'] == "difsep") echo "selected"; ?>>Inny</option>
                </select>
                <input type="text" class="form-control w-20" name="difsep" id="difsep" placeholder="Inny separator" <?php if (!empty($_POST['difsep']) && (isset($_POST['separator']) && $_POST['separator'] == "difsep")) echo 'value="' . $_POST['difsep'] . '"'; if (!(isset($_POST['separator']) && $_POST['separator'] == "difsep")) echo " disabled" ?>>
                <button type="button" class="btn btn-outline-danger w-20" onclick="wyczysc()">Wyczyść</button>
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
        <?php endif; ?>
        <div class="m-4">
            <button type="button" class="btn btn-sm btn-outline-light my-2" onclick="downloadTable()"><i class="bi bi-download"></i> Pobierz tabelę</button>
            <div><small>Podwójne kliknięcie na składnik, nr CAS lub nr WE kopiuje go do schowka</small></div>
            <table class="table table-hover table-sm align-middle">
                <thead>
                    <tr>
                        <th scope="col" class="dwn">INCI</th>
                        <?php if ($fail): ?>
                        <th scope="col">Podpowiedź</th>
                        <?php else: ?>
                        <th scope="col" class="dwn">Nr CAS</th>
                        <th scope="col" class="dwn">Nr WE <sup><span class="text-info" data-bs-toggle="tooltip" data-bs-title="Inne nazwy numeru WE: EC number / EINECS / ELINCS / No-longer polymers"><i class="bi bi-info-circle"></i></span></sup></th>
                        <th scope="col">Zał. 1223/2009</th>
                        <th scope="col" class="dwn">Funkcja</th>
                        <th scope="col" class="dwn visually-hidden">Function</th>
                        <th scope="col" class="text-center">CosIng</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="table-group-divider">
                    <?php foreach ($incitest as $ingredient) { 
                        // If nano...
                        if (str_contains($ingredient,"(nano)")) {
                            // If yes then cut-off nano part and check if ingredient is correct
                            $temping = trim(str_replace("(nano)","",$ingredient));
                            if (in_array(strtoupper($temping),$slownik)) {
                                $test = true;
                                $key = array_search(strtoupper($temping),$slownik);
                            } else {
                                $test = false;
                                $podpowiedz = wyszukajpodpowiedz($temping,$slownik);
                            }
                        } else {
                            if (in_array(strtoupper($ingredient),$slownik)) {
                                $test = true;
                                $key = array_search(strtoupper($ingredient),$slownik);
                            } else {
                                $test = false;
                                $podpowiedz = wyszukajpodpowiedz($ingredient,$slownik);
                            }
                        }
                    ?>
                        <tr>
                            <th scope="row"  class="dwn<?php if (!$test) echo ' text-danger'; if ($test && !empty($duplicates) && in_array(strtoupper($ingredient),$duplicates)) echo ' text-warning'; ?>"><span class="user-select-all" ondblclick="copyInci(this)"><?php echo lettersize($ingredient); ?></span></th>
                            <?php if ($fail): ?>
                            <td class="font-sm"><?php if (!$test) echo $podpowiedz; ?></td>
                            <?php else: ?>
                            <td class="dwn"><span class="user-select-all font-monospace" ondblclick="copyInci(this)"><?php echo $ingredients[$key]['cas']; ?></span></td>
                            <td class="dwn"><span class="user-select-all font-monospace" ondblclick="copyInci(this)"><?php echo $ingredients[$key]['we']; ?></span></td>
                            <td><?php 
                                if (str_contains($ingredients[$key]['annex'],"I/") || str_contains($ingredients[$key]['annex'],"V/")) {
                                    if (str_contains($ingredients[$key]['annex'],'#')) {
                                        echo '<a href="#ingredient" class="text-reset" data-bs-toggle="modal">'. trim(substr($ingredients[$key]['annex'],0,strpos($ingredients[$key]['annex'],'#'))) .'</a> '. substr($ingredients[$key]['annex'],strpos($ingredients[$key]['annex'],'#'));
                                    } else {
                                        echo '<a href="#ingredient" class="text-reset" data-bs-toggle="modal">'. $ingredients[$key]['annex'] .'</a>';
                                    }
                                } else {
                                    echo $ingredients[$key]['annex']; 
                                }
                            ?></td>
                            <td class="dwn"><?php foreach (explode(" | ",$ingredients[$key]['function']) as $function) {$ingfunc[] = $funcdict[$function]['pl']; }; echo implode(", ",$ingfunc); unset($ingfunc); ?></td>
                            <td class="dwn visually-hidden"><?php foreach (explode(" | ",$ingredients[$key]['function']) as $function) {$ingfunc[] = $funcdict[$function]['en']; }; echo implode(", ",$ingfunc); unset($ingfunc); ?></td>
                            <td class="text-center"><?php if (!empty($ingredients[$key]['ref'])) echo '<a class="text-reset link-underline link-underline-opacity-0" target="_blank" title="Link do składnika w CosIng" href="https://ec.europa.eu/growth/tools-databases/cosing/details/'.$ingredients[$key]['ref'].'"><i class="bi bi-info-circle"></i></a>';?></td>
                            <?php endif; ?>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="container">
        <?php if (!empty($done)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong>Twoja uwaga została zapisana!</strong>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        <h1 class="text-center">Pojedynczy składnik</h1>
        <div class="card col-lg-4 mx-auto p-3 mt-3">
            <form method="post" class="text-center">
                <div class="card-body">
                    <div class="row g-4 align-items-center text-end">
                        <label for="inci-name" class="form-label col-3 fw-bold">INCI</label>
                        <div class="col-9">
                            <input type="text" class="form-control" name="inci" id="inci"<?php echo !empty($_POST['inci']) ? 'value="'.$_POST['inci'].'"': ''; ?>>
                        </div>
                        <label for="cas" class="form-label col-3 fw-bold">CAS</label>
                        <div class="col-9">
                            <input type="text" class="form-control" name="cas" id="cas"<?php echo !empty($_POST['cas']) ? 'value="'.$_POST['cas'].'"': ''; ?>>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline-primary mt-3 w-50" name="single">Sprawdź</button>
                </div>
            </form>
        </div>
    </div>
    <div class="container-fluid">
        <?php
        foreach ($searchedkeys as $key) {
            foreach ($ingredients[$key] as $value) {
                echo $value . "<br>";
            }
        }
        ?>
    </div>
    <?php endif; ?>
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
    <div class="position-fixed start-50 translate-middle-x top-0 mt-5">
        <div class="toast fade text-bg-light" role="alert" data-bs-delay="1500">
            <div class="toast-body fw-bold fs-6 text-center">
                <p class="mb-1">Skopiowano do schowka:</p>
                <span class="fst-italic"></span>
            </div>
        </div>
    </div>
    <script>
        function wyczysc() {
            const inci = document.querySelector('#inci');
            inci.innerText = '';
            inci.value = '';
        }
        function copyInci(span) {
            navigator.clipboard.writeText(span.innerText);
            const toast = document.querySelector('.toast');
            toast.querySelector('span').innerText = span.innerText;
            toastOn = bootstrap.Toast.getOrCreateInstance(toast);
            toastOn.show();
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

            csvFile = new Blob([csvData],{type: "tex/csv"});
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
                if (inciName.includes("(nano)")) {
                    inciName = inciName.replace("(nano)","");
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
    </script>
</body>
</html>