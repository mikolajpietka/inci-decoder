import os
import json
import time
import requests
from bs4 import BeautifulSoup
from multiprocessing import Process
from seleniumwire import webdriver
from seleniumwire.utils import decode

def scraper(driver, url, timeout=15):
    try:
        driver.get(url)
        driver.wait_for_request("api.tech.ec.europa.eu/cosing20/1.0/api/cosmetics/",timeout=timeout)
        data = []
        for request in driver.requests:
            if "api.tech.ec.europa.eu/search-api/prod/rest/search" in request.url:
                data.append(str(decode(request.response.body, request.response.headers.get('Content-Encoding', 'identity')), encoding="utf-8"))
            if "api.tech.ec.europa.eu/cosing20/1.0/api/cosmetics" in request.url and request.response.status_code == 200:
                data.append(request.response.body)
        if len(data) == 0:
            raise Exception
        return data
    except Exception:
        return None
    
def responsehandler(list, timeout=15):
    driver = webdriver.Chrome()
    driver.minimize_window()
    if not os.path.exists("img/"): 
        os.mkdir("img")
    if not os.path.exists("datatools/data/"): 
        os.mkdir("datatools/data")
    if not os.path.exists("datatools/timeout/"): 
        os.mkdir("datatools/timeout")
    for number in list:
        url = f"https://ec.europa.eu/growth/tools-databases/cosing/details/{number}"
        message = "Scraping data from: " + url + "\n"
        responses = scraper(driver,url,timeout)
        if responses == None:
            t = open(f"datatools/timeout/{number}","w")
            t.close()
            message += "Timeout..."
        else:
            for resp in responses:
                if isinstance(resp,str):
                    data = json.loads(resp)
                    if len(data["results"]) != 0 and (data["results"][0]["metadata"]["substanceId"][0]) == str(number):
                        with open(f"datatools/data/{number}.json",mode="w",encoding="utf-8") as f:
                            json.dump(data,f,indent=4)
                            f.close()
                            message += "Scraped data!\n"
                if isinstance(resp,bytes):
                    with open(f"img/{number}.gif","wb") as g:
                        g.write(resp)
                        g.close()
                        message += "Scraped image!\n"
        del driver.requests
        print(message)
    print("Quiting scraping")
    driver.quit()

def processmaker(fromNo,toNo,processes=1):
    print(f"Starting multiprocess scraping (from number {fromNo} to number {toNo} in {processes} processes)")
    start = time.time()
    arglist = {}
    total = []
    process = {}
    for x in range(fromNo,toNo+1):
        total.append(x)
    for l in range(processes):
        arglist[l] = total[l::processes]
    
    for p in arglist:
        process[p] = Process(target=responsehandler,args=(arglist[p], ))
        process[p].start()
        process[p].join()
    end = time.time()
    elapsed = end - start
    print(f"Processess ended, it took {elapsed} seconds")

def leftovers(timeout=60):
    if os.path.exists("datatools/timeout"):
        timeouted = os.listdir("datatools/timeout")
        howmany = len(timeouted)
        if howmany == 0:
            print("There is no leftovers")
            exit()
        os.rename("datatools/timeout","datatools/timeouted")
        print(f"Checking again leftovers - {howmany} reference numbers")
        responsehandler(timeouted,timeout)
    else:
        print("There is no directory of timeouts")

def cosmilescrap():
    incifile = "INCI.json"
    cosmilefile = "datatools/cosmile.json"
    with open(incifile,"r",encoding="utf-8") as of:
        inci = json.load(of).keys()
        of.close()
    print(f"Starting scraping COSMILE - there is {len(inci)} to check")
    time.sleep(3)
    start = time.time()
    cosmile = {}
    for ingredient in inci:
        print("Scraping COSMILE for INCI: " + ingredient)
        parameters = {
            "s":ingredient,
            "l":"pl-PL",
            "n":10,
            "p":"https://cosmileeurope.eu/pl/inci/szczegoly"
        }
        r = requests.get("https://cosmileeurope.eu/wp-content/plugins/inci-db-search/search.php",params=parameters)
        r.encoding = "utf-8"
        soup = BeautifulSoup(r.text,"html.parser")
        links = soup.find_all("a")
        if len(links) != 0:
            for link in links:
                if link.contents[0] == ingredient:
                    value = link["href"].replace("https://cosmileeurope.eu/pl/inci/szczegoly/","")
                    cosmile[ingredient] = value
                    print("Done!")
                    break
                else:
                    cosmile[ingredient] = None
                    print("Link not correct")
        else:
            cosmile[ingredient] = None
            print("No such ingredient in COSMILE")
    with open(cosmilefile,"w",encoding="utf-8") as cosf:
        json.dump(cosmile,cosf,indent=4)
        cosf.close()
    end = time.time()
    elapsed = end - start
    print(f"Processess ended, it took {elapsed} seconds")

if __name__ == "__main__":
    # Add input to choose if whole range of ingredients shoud be checked and how many processes
    # processmaker(27900,106000,4)
    # leftovers()
    cosmilescrap()