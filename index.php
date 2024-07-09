<?php 
error_reporting(0);
$pagetitle = "Sprawdzanie INCI";

function wielkoscliterinci($text) {
    $rp = [
        1 => [
            't' => 't',
            'o' => 'o',
            'p' => 'p',
            'm' => 'm'
        ],
        2 => [
            'se' => 'SE',
            'ci' => 'CI',
            'np' => 'NP',
            'vp' => 'VP',
            'va' => 'VA',
            'ap' => 'AP',
            '9m' => '9M',
            'pg' => 'PG',
            'hc' => 'HC'
        ],
        3 => [
            'bht' => 'BHT',
            'bha' => 'BHA',
            'peg' => 'PEG',
            'ppg' => 'PPG',
            'hcl' => 'HCl',
            'hbr' => 'HBr',
            'eop' => 'EOP',
            'dea' => 'DEA',
            'mea' => 'MEA',
            'fcf' => 'FCF',
            '90m' => '90M',
            '45m' => '45M',
            'pca' => 'PCA',
            'pvp' => 'PVP'
        ],
        4 => [
            'edta' => 'EDTA',
            'tbhq' => 'TBHQ',
            'dmdm' => 'DMDM',
            'mipa' => 'MIPA',
            'dipa' => 'DIPA'
        ]
    ];
    foreach(explode(', ',$text) as $ingredient) {
        foreach (explode(' ',$ingredient) as $word) {
            $word = strtolower($word);
            if (str_contains($word,'/')) {
                foreach(explode('/',$word) as $part) {
                    if (strlen($part) == 2) {
                        $parts[] = strtr($part,$rp[2]);
                    } elseif (strlen($part) == 3) {
                        $parts[] = strtr($part,$rp[3]);
                    } elseif (strlen($part) == 4) {
                        $parts[] = strtr($part,$rp[4]);
                    } else {
                        $parts[] = ucfirst($part);
                    }
                } 
                foreach ($parts as $secword) {
                    if (str_contains($secword,'-')) {
                        foreach(explode('-',$secword) as $secpart) {
                            if (strlen($secpart) == 2) {
                                $secparts[] = ucfirst(strtr(strtolower($secpart),$rp[2]));
                            } elseif (strlen($secpart) == 3) {
                                $secparts[] = ucfirst(strtr(strtolower($secpart),$rp[3]));
                            } elseif (strlen($secpart) == 4) {
                                $secparts[] = ucfirst(strtr(strtolower($secpart),$rp[4]));
                            } else {
                                $secparts[] = ucfirst(strtolower($secpart));
                            }
                        }
                        $newerword[] = implode('-',$secparts);
                        unset($parts);
                        unset($secparts);
                    } else {
                        $newerword[] = ucfirst($secword);
                    }
                }
                $newword[] = implode('/',$newerword);
                unset($newerword);
                unset($parts);
            } else {
                if (str_contains($word,'-')) {
                    foreach (explode('-',$word) as $part) {
                        if (strlen($part) == 2) {
                            $parts[] = ucfirst(strtr($part,$rp[2]));
                        } elseif (strlen($part) == 3) {
                            $parts[] = ucfirst(strtr($part,$rp[3]));
                        } elseif (strlen($part) == 4) {
                            $parts[] = ucfirst(strtr($part,$rp[4]));
                        } else {
                            $parts[] = ucfirst($part);
                        }
                    }
                    $newword[] = implode('-',$parts);
                    unset($parts);
                } else {
                    if (strlen($word) == 2) {
                        $newword[] = ucfirst(strtr(strtolower($word),$rp[2]));
                    } elseif (strlen($word) == 3) {
                        $newword[] = ucfirst(strtr(strtolower($word),$rp[3]));
                    } elseif (strlen($word) == 4) {
                        $newword[] = ucfirst(strtr(strtolower($word),$rp[4]));
                    } else {
                        $newword[] = ucfirst($word);
                    }
                }
            }
        }
        if (strlen($ingredient) == 2) {
            $newingredient[] = ucfirst(strtr(strtolower($ingredient),$rp[2]));
        } elseif (strlen($ingredient) == 3) {
            $newingredient[] = ucfirst(strtr(strtolower($ingredient),$rp[3]));
        } elseif (strlen($ingredient) == 4) {
            $newingredient[] = ucfirst(strtr(strtolower($ingredient),$rp[4]));
        } else {
            $newingredient[] = implode(' ',$newword);
        }
        unset($newword);
    }
    return implode(', ',$newingredient);
}

function wyszukajpodpowiedz($text,$array) {
    $text = strtoupper($text);
    $raw = array_filter($array, function($v,$k) use ($text) {
        if (str_starts_with($v,substr($text,0))) {
            return $v;
        }
    }, ARRAY_FILTER_USE_BOTH);
    foreach ($raw as $inci) {
        $podpowiedz[] = '<span class="user-select-all" ondblclick="copyInci(this)">' . wielkoscliterinci($inci) . '</span>';
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
                    foreach ($fileraw as $row) {
                        $file[$row[0]] = [
                            'substance' => $row[1],
                            'CAS' => $row[2],
                            'WE' => $row[3]
                        ];
                    } ?>
                    <div class="mt-2">
                        <h5>Załącznik II: Wykaz substancji zakazanych w produktach kosmetycznych</h5>
                        <table class="table">
                            <tr>
                                <th scope="row">Indeks</th>
                                <td><?php echo $anx; ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Nazwa chemiczna / INN</th>
                                <td><?php echo $file[$annex[1]]['substance']; ?></td>
                            </tr>
                            <tr>
                                <th scope="row">nr CAS</th>
                                <td><?php echo $file[$annex[1]]['CAS']; ?></td>
                            </tr>
                            <tr>
                                <th scope="row">nr WE</th>
                                <td><?php echo $file[$annex[1]]['WE']; ?></td>
                            </tr>
                        </table>
                    </div>
                    <?php
                } else {
                    echo 'Błąd odczytu pliku! Odśwież stronę i spróbuj ponownie';
                    exit;
                }
                break;
            case 'III':
                if (file_exists('A3.csv') && !empty($fileraw = array_map('str_getcsv', file('A3.csv')))) {
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
                    <div class="mt-2">
                        <h5>Załącznik III: Wykaz substancji, które mogą być zawarte w produktach kosmetycznych wyłącznie z zastrzeżeniem określonych ograniczeń</h5>
                        <strong>Załącznik wstępnie przeredagowany</strong>
                        <table class="table">
                            <tr>
                                <th scope="row">Indeks</th>
                                <td><?php echo $anx; ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Nazwa chemiczna / INN</th>
                                <td><?php echo $file[$annex[1]]['inn']; ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Nazwa w słowniku wspólnych nazw / INCI</th>
                                <td><?php echo $file[$annex[1]]['inci']; ?></td>
                            </tr>
                            <tr>
                                <th scope="row">nr CAS</th>
                                <td><?php echo $file[$annex[1]]['cas']; ?></td>
                            </tr>
                            <tr>
                                <th scope="row">nr WE</th>
                                <td><?php echo $file[$annex[1]]['we']; ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Rodzaj produktu, części ciała</th>
                                <td><?php echo $file[$annex[1]]['type']; ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Maksymalne stężenie w preparacie gotowym do użycia</th>
                                <td><?php echo $file[$annex[1]]['max']; ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Inne</th>
                                <td><?php echo $file[$annex[1]]['other']; ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Warunki i ostrzeżenia na opakowaniach</th>
                                <td><?php echo $file[$annex[1]]['conditions']; ?></td>
                            </tr>
                        </table>
                    </div>
                    <?php
                } else {
                    echo 'Błąd odczytu pliku! Odśwież stronę i spróbuj ponownie';
                    exit;
                }
                break;
            case 'IV':
                echo '<h5>Załącznik IV niegotowy do wyświetlenia</h5>';
                break;
            case 'V':
                echo '<h5>Załącznik V niegotowy do wyświetlenia</h5>';
                break;
            case 'VI':
                echo '<h5>Załącznik VI niegotowy do wyświetlenia</h5>';
                break;
        }
    }
    exit;
}

$csv = array_map('str_getcsv', file('INCI.csv'));
foreach ($csv as $ingredient) {
    $ingredients[] = [
        'name' => $ingredient[1],
        'cas' => $ingredient[2],
        'we' => $ingredient[3],
        'annex' => $ingredient[4]
    ];
}
$slownik = array_column($ingredients,'name');

if (isset($_POST['inci'])) {
    if (!empty($_POST['inci'])) {
        $inciexp = explode(', ',str_replace("\n","",$_POST['inci']));
        foreach ($inciexp as $ingredient) {
            $incitest[] = trim($ingredient);
        }
        $fail = 0;
        foreach ($incitest as $ingredient) { 
            if (!in_array(strtoupper($ingredient),$slownik)) {
                $fail = 1;
            }
        }
    }
}
?>
<!DOCTYPE HTML>
<html lang="pl" data-bs-theme="dark">
<head>   
    <title><?php echo $pagetitle." | ".$_SERVER['HTTP_HOST']; ?></title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Private server for Friends">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Material icons -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined">
    <!-- Page CSS -->
    <link href="styles.css?ver=2.0.inci" rel="stylesheet">
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="favicon.ico">
</head>
<body class="bg-dark">
    <div class="container my-3">
        <h1>Skład kosmetyku do sprawdzenia</h1>
        <form method="post">
            <textarea class="form-control" id="inci" name="inci" rows="9"><?php echo !empty($_POST['inci']) ? wielkoscliterinci(str_replace("\n","",$_POST['inci'])) : ""; ?></textarea>
            <div class="d-flex gap-3 mt-3">
                <button type="submit" class="btn btn-outline-light">Sprawdź</button>
                <button type="button" class="btn btn-outline-danger" onclick="wyczysc()">Wyczyść</button>
            </div>
        </form>
        <?php if (isset($incitest)): 
        if ($fail): ?>
            <div class="text-danger fw-bold fs-3 mt-2">Błędne INCI <i class="bi bi-emoji-frown-fill"></i></div>
        <?php else: ?>
            <div class="text-success fw-bold fs-3 mt-2">Poprawne INCI <i class="bi bi-hand-thumbs-up-fill"></i></div>
            <button type="button" class="btn btn-primary" onclick="downloadTable()">Pobierz tabelę</button>
        <?php endif; ?>
    </div>
    <div class="container-fluid ingredients">
        <div class="m-4">
            <small>Podwójne kliknięcie na składnik, nr CAS lub nr WE kopiuje go do schowka</small>
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th scope="col">INCI</th>
                        <?php if ($fail): ?>
                        <th scope="col">Podpowiedź</th>
                        <?php else: ?>
                        <th scope="col">CAS</th>
                        <th scope="col">WE</th>
                        <th scope="col">Annex</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody class="table-group-divider">
                    <?php foreach ($incitest as $ingredient) { 
                        if (in_array(strtoupper($ingredient),$slownik)) {
                            $test = 1;
                            $key = array_search(strtoupper($ingredient),$slownik);
                        } else {
                            $test = 0;
                            $podpowiedz = wyszukajpodpowiedz($ingredient,$slownik);
                        }
                    ?>
                        <tr>
                            <th scope="row" <?php if (!$test) echo 'class="text-danger"'; ?>><?php echo '<span class="user-select-all" ondblclick="copyInci(this)">' . wielkoscliterinci($ingredient) . '</span>'; ?></th>
                            <?php if ($fail): ?>
                            <td class="font-sm"><?php if (!$test) echo $podpowiedz; ?></td>
                            <?php else: ?>
                            <td><span class="user-select-all font-monospace" ondblclick="copyInci(this)"><?php echo $ingredients[$key]['cas']; ?></span></td>
                            <td><span class="user-select-all font-monospace" ondblclick="copyInci(this)"><?php echo $ingredients[$key]['we']; ?></span></td>
                            <td><?php 
                                if (str_contains($ingredients[$key]['annex'],"I/") || str_contains($ingredients[$key]['annex'],"V/")) {
                                    if (str_contains($ingredients[$key]['annex'],'#')) {
                                        echo '<a href="#annex" class="text-reset" data-bs-toggle="modal">'. trim(substr($ingredients[$key]['annex'],0,strpos($ingredients[$key]['annex'],'#'))) .'</a> '. substr($ingredients[$key]['annex'],strpos($ingredients[$key]['annex'],'#'));
                                    } else {
                                        echo '<a href="#annex" class="text-reset" data-bs-toggle="modal">'. $ingredients[$key]['annex'] .'</a>';
                                    }
                                } else {
                                    echo $ingredients[$key]['annex']; 
                                }
                            ?></td>
                            <?php endif; ?>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <div class="modal fade" id="annex" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-scrollable modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-body">
                    <h1 class="fs-3 modal-title fst-italic"></h1>
                    <div class="annexes">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Ładowanie...</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Zamknij</button>
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
    <?php if (0): ?>
    <div class="container-fluid">
        <table class="table">
            <?php
            $annex2 = array_map('str_getcsv',file('A3.csv'));
            foreach ($annex2 as $row) {
                echo '<tr>';
                foreach ($row as $column) {
                    echo '<td>' . $column . '</td>';
                }
                echo '</tr>';
            }
            ?>
        </table>
    </div>
    <?php endif; ?>
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
                let tableCols = x.querySelectorAll('th,td');
                let csvCol = [];
                tableCols.forEach(x => {
                    csvCol.push('"'+x.innerText+'"');
                });
                csvRow.push(csvCol.join(","));
            });
            let csvData = csvRow.join('\n');

            csvFile = new Blob([csvData],{type: "tex/csv"});
            let tempLink = document.createElement("a");
            tempLink.download = "Ingredients.csv";
            tempLink.href = window.URL.createObjectURL(csvFile);
            tempLink.style.display = "none";
            document.body.appendChild(tempLink);
            tempLink.click();
            tempLink.remove();
        }

        const annexModal = document.querySelector('#annex');
        if (annexModal) {
            annexModal.addEventListener('show.bs.modal',event => {
                const link = event.relatedTarget;
                const request = encodeURI(link.innerText);
                const inciName = link.parentElement.parentElement.querySelector('th').innerText;
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
    </script>
</body>
</html>
<?php 
// <sup><span class='text-info' data-bs-toggle='tooltip' data-bs-title=''></span></sup>
?>