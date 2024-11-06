function getCookie(name) {
    const cname = name + "="
    const decodedCookie = decodeURIComponent(document.cookie)
    const splitCookie = decodedCookie.split("; ")
    for (let i=0; i < splitCookie.length; i++) {
        x = splitCookie[i]
        if (x.indexOf(cname) == 0) {
            return (x.substring(cname.length,x.length));
        }
    }
    return null
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
function copyinci() {
    const inci = document.querySelector('#inci').value;
    navigator.clipboard.writeText(inci);
    const toast = document.querySelector('.toast');
    toast.querySelector('p').innerText = "Skopiowano do schowka:";
    toast.querySelector('span').innerText = inci;
    toastOn = bootstrap.Toast.getOrCreateInstance(toast);
    toastOn.show();
    window.getSelection().removeAllRanges();
}
function pasteinci() {
    const textarea = document.querySelector('#inci');
    navigator.clipboard.readText().then((topaste) => (textarea.value = topaste));
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

const exeur = parseFloat(getCookie("exchange_eur"));
const exusd = parseFloat(getCookie("exchange_usd"));
const exdate = getCookie("exchange_date");

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
        };
        xhttp.open('GET','?details='+encodeURI(ingredient));
        xhttp.send();
    })
    detailsModal.addEventListener("hidden.bs.modal", event => {
        detailsModal.querySelector(".modal-body").innerHTML = '<div class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Ładowanie...</span></div></div>';
    })
}

function getsuggestions(button) {
    const row = button.parentElement.parentElement;
    const percent = row.querySelector(".percent").innerText - 5;
    const suggestioncell = row.querySelector("td");
    const mistake = row.querySelector("th").innerText;
    const xhttp = new XMLHttpRequest();
    xhttp.onload = function() {
        response = JSON.parse(xhttp.responseText);
        let chosen = suggestioncell.querySelector(".text-success");
        let choseninci = "";
        if (chosen) {
            choseninci = chosen.innerText;
        }
        let toinsertlist = [];
        let classes = ""
        response["suggestions"].forEach(element => {
            if (element["inci"] == choseninci) {
                classes = "user-select-all nowrap text-success";
            } else {
                classes = "user-select-all nowrap";
            }
            toinsertlist.push('<span class="' + classes + '" data-bs-toggle="tooltip" data-bs-title="Podobieństwo:' + element["similarity"] + '%" ondblclick="correctmistake(this)">' + element["inci"] + '</span>'); 
        });
        const toinsert = toinsertlist.join(", ") + '<i class="d-none percent">' + response["get_percent"] + '</i>';
        suggestioncell.innerHTML = toinsert;
        const tooltipTriggerList = document.querySelectorAll("[data-bs-toggle='tooltip']");
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    }
    xhttp.open('GET','?suggest='+encodeURI(mistake)+'&percent='+percent);
    xhttp.send();
}