function getCookie(name) {
    const cname = name + "=";
    const decodedCookie = decodeURIComponent(document.cookie);
    const splitCookie = decodedCookie.split("; ")
    for (let i=0; i < splitCookie.length; i++) {
        x = splitCookie[i]
        if (x.indexOf(cname) == 0) return (x.substring(cname.length,x.length));
    }
    return null;
}
function activateTooltips() {
    const tooltipTriggerList = document.querySelectorAll("[data-bs-toggle='tooltip']");
    [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
}
function notify(header,content = null) {
    const toast = document.querySelector('.toast');
    toast.querySelector('p').innerText = header;
    const truncatelen = 350;
    if (content.length > truncatelen) {
        content = content.substring(0,truncatelen) + "...";
    }
    toast.querySelector('span').innerText = content;
    // Show notification
    toastOn = bootstrap.Toast.getOrCreateInstance(toast);
    toastOn.show();
}
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
    if (tofocus = document.querySelector("#inci,#inci-model")) tofocus.focus();
}
function copyText(text) {
    navigator.clipboard.writeText(text);
    notify("Skopiowano do schowka",text);
    window.getSelection().removeAllRanges();
}
function pasteinci() {
    const textarea = document.querySelector('#inci');
    navigator.clipboard.readText().then((topaste) => (textarea.value = topaste));
    notify("Wklejono zawartość schowka");
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
    // Create Blob and download it
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
function getLetterSize(request) {
    const encodedRequest = encodeURI(request);
    const xhttp = new XMLHttpRequest();
    xhttp.open("GET","?lettersize="+encodedRequest)
    xhttp.send();
    return xhttp;
}
function getAnnex(request) {
    const encodedRequest = encodeURI(request);
    const xhttp = new XMLHttpRequest();
    xhttp.open("GET","?anx="+encodedRequest)
    xhttp.send();
    return xhttp;
}
const throbber = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Ładowanie...</span></div></div>';
const annexIngredient = document.querySelector("#ingredientAnnex");
if (annexIngredient) {
    annexIngredient.addEventListener('show.bs.modal', event => {
        annexIngredient.querySelector('.modal-title').innerText = event.relatedTarget.parentElement.parentElement.querySelector('th').innerText.replace(" (nano)","");
        const response = getAnnex(event.relatedTarget.innerText);
        response.onload = function() {
            annexIngredient.querySelector(".annexes").innerHTML = response.responseText;
            activateTooltips();
        }
    });
    annexIngredient.addEventListener('hidden.bs.modal', _event => {
        annexIngredient.querySelector(".annexes").innerHTML = throbber;
    })
}
const annexWhole = document.querySelector('[name="wholeAnnex"]');
if (annexWhole) {
    annexWhole.addEventListener('change', event => {
        const modalBody = document.querySelector('#wholeAnnex .modal-body');
        if (event.target.value != 0) {
            const response = getAnnex(event.target.value);
            modalBody.innerHTML = throbber;
            response.onload = function() {
                modalBody.innerHTML = response.responseText;
                activateTooltips();
            }
        } else {
            modalBody.innerHTML = '<h2>Wybierz załącznik...</h2>';
        }
    })
}

const separator = document.querySelector("#separator");
const difsep = document.querySelector("#difsep");
if (separator) {
    separator.addEventListener("change",_event => {
        if (separator.value == "difsep") {
            difsep.disabled = false;
        } else {
            difsep.disabled = true;
        }
    })
}

document.addEventListener("keydown",event=>{
    if (event.ctrlKey && event.key === "Enter") {
        document.querySelector("#submit").click();
    }
    if (event.ctrlKey && event.key === "Delete" && document.activeElement !== document.querySelector("#inci")) {
        cleartextarea();
    }
    if (event.key === "Escape") {
        document.activeElement.blur();
    }
    if (event.ctrlKey && event.key === "Insert") {
        const toolsModal = new bootstrap.Modal("#tools");
        toolsModal.show();
    }
    if (event.ctrlKey && event.shiftKey && event.key === "Q") {
        const searchModal = new bootstrap.Modal("#searchINCI");
        searchModal.show();
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
    const prevtext = getCookie("inci");
    textarea.value = prevtext;
    const separator = document.querySelector("#separator");
    const prevseparator = getCookie("separator");
    separator.value = prevseparator;
    const difsep = document.querySelector("#difsep");
    const prevdifsep = getCookie("difsep");
    difsep.value = prevdifsep;
    const connector = document.querySelector("#connector");
    prevconnector = getCookie("connector");
    connector.checked = prevconnector;
}

const search = document.querySelector("#search");
const microplastics = document.querySelector("#microplastics");
search.addEventListener("input",_event => {
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
    microplastics.addEventListener("show.bs.modal",_event => {
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

const currency = document.querySelector("#currency");
const eur = currency.querySelector("#eur");
const plneur = currency.querySelector("#plneur");
const usd = currency.querySelector("#usd");
const plnusd = currency.querySelector("#plnusd");
const exdatespan = currency.querySelector(".modal-body p span");

const exeur = parseFloat(getCookie("exchange_eur"));
const exusd = parseFloat(getCookie("exchange_usd"));
const exdate = getCookie("exchange_date");

eur.addEventListener("input",_event => {
    plneur.value = (eur.value * exeur).toFixed(2);
})
plneur.addEventListener("input",_event => {
    eur.value = (plneur.value / exeur).toFixed(2);
})
usd.addEventListener("input",_event => {
    plnusd.value = (usd.value * exusd).toFixed(2);
})
plnusd.addEventListener("input",_event => {
    usd.value = (plnusd.value / exusd).toFixed(2);
})
currency.addEventListener("show.bs.modal", _event => {
    eur.value = (1).toFixed(2);
    plneur.value = exeur.toFixed(2);
    usd.value = (1).toFixed(2);
    plnusd.value = exusd.toFixed(2);
    exdatespan.innerText = exdate;
});

const detailsModal = document.querySelector("#details");
if (detailsModal) {
    detailsModal.addEventListener("show.bs.modal", event => {
        const showlink = event.relatedTarget;
        const ingredient = showlink.parentElement.parentElement.querySelector("th").innerText.replace(" (nano)","");
        const xhttp = new XMLHttpRequest();
        xhttp.onload = function() {
            detailsModal.querySelector(".modal-body").innerHTML = xhttp.responseText;
            activateTooltips();
        };
        xhttp.open('GET','?details='+encodeURI(ingredient));
        xhttp.send();
    })
    detailsModal.addEventListener("hidden.bs.modal", _event => {
        detailsModal.querySelector(".modal-body").innerHTML = throbber;
    })
}
const miniThrobber = '<div class="spinner-border spinner-border-sm text-primary" role="status"><span class="visually-hidden">Loading...</span></div>'
function getsuggestions(button) {
    const row = button.parentElement.parentElement;
    const percent = row.querySelector(".percent").innerText - 5;
    const suggestioncell = row.querySelector("td");
    const chosen = suggestioncell.querySelector(".text-success");
    let choseninci = "";
    if (chosen) {
        choseninci = chosen.innerText;
    }
    suggestioncell.innerHTML = miniThrobber;
    const mistake = row.querySelector("th").innerText;
    const xhttp = new XMLHttpRequest();
    xhttp.onload = function() {
        response = JSON.parse(xhttp.responseText);
        let toinsertlist = [];
        let classes = ""
        if (response["suggestions"] != null) {
            response["suggestions"].forEach(element => {
                if (element["inci"] == choseninci) {
                    classes = "user-select-all nowrap text-success";
                } else {
                    classes = "user-select-all nowrap";
                }
                toinsertlist.push('<span class="' + classes + '" data-bs-toggle="tooltip" data-bs-title="Podobieństwo:' + element["similarity"] + '%" ondblclick="correctmistake(this)">' + element["inci"] + '</span>'); 
            });
        } else {
            toinsertlist.push('<span class="fst-italic">Brak podpowiedzi w tym zakresie, kliknij "Pokaż więcej" żeby zwiększyć zakres</span>');
        }
        suggestioncell.innerHTML = toinsertlist.join(", ") + '<i class="d-none percent">' + response["get_percent"] + '</i>';
        activateTooltips()
    }
    xhttp.open('GET','?suggest='+encodeURI(mistake)+'&percent='+percent);
    xhttp.send();
}

const tools = document.querySelector("#tools");
if (tools) {
    const inToUpper = tools.querySelector("#toupper");
    inToUpper.addEventListener("input", _event => {
        tools.querySelector("#out-toupper").innerText = inToUpper.value.toUpperCase();
    })
    const lettersize = tools.querySelector("#lettersize");
    const outLettersize = tools.querySelector("#out-lettersize")
    lettersize.addEventListener("input", _event => {
        if (lettersize.value != "") {
            const response = getLetterSize(lettersize.value);
            outLettersize.innerHTML = miniThrobber;
            response.onload = function() {
                const parsed = JSON.parse(response.response)
                outLettersize.innerText = parsed["converted"];
            }
        } else {
            outLettersize.innerText = "";
        }
    })
}

function checkAll(selector,check) {
    const object = document.querySelector(selector);
    const checkboxes = object.querySelectorAll("[type='checkbox']");
    checkboxes.forEach(x => {
        x.checked = check;
    })
}

function sendForm(formElement,url) {
    const formData = new FormData(formElement);
    const xhttp = new XMLHttpRequest();
    xhttp.open("POST",url);
    xhttp.send(formData);
    return xhttp;
}

const searchINCI = document.querySelector("#searchINCI");
const searchForm = searchINCI.querySelector("form");
const searchResponse = searchINCI.querySelector("#search-response");
if (searchForm) {
    searchForm.addEventListener("submit",event => {
        searchResponse.innerHTML = throbber;
        event.preventDefault();
        const response = sendForm(searchForm,"?search");
        response.onload = function() {
            searchResponse.innerHTML = response.response;
        }
    })
}

activateTooltips()