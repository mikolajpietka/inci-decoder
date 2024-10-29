import os
import json
import time
from multiprocessing import Process
from seleniumwire import webdriver
from seleniumwire.utils import decode

def scraper(driver, url):
    try:
        driver.get(url)
        driver.wait_for_request("api.tech.ec.europa.eu/cosing20/1.0/api/cosmetics/",timeout=15)
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
    
def responsehandler(list):
    driver = webdriver.Chrome()
    driver.minimize_window()
    if not os.path.exists("img/"): 
        os.mkdir("img")
    if not os.path.exists("datatools/data/"): 
        os.mkdir("datatools/data")
    if not os.path.exists("datatools/timeout/"): 
        os.mkdir("datatools/timeout")
    timeout = 0
    for number in list:
        if timeout >=5: break
        url = f"https://ec.europa.eu/growth/tools-databases/cosing/details/{number}"
        message = "Scraping data from: " + url + "\n"
        responses = scraper(driver,url)
        if responses == None:
            t = open(f"datatools/timeout/{number}","w")
            t.close()
            message += "Timeout..."
            timeout += 1
        else:
            for resp in responses:
                if isinstance(resp,str):
                    data = json.loads(resp)
                    if len(data["results"]) != 0 and int(data["results"][0]["metadata"]["substanceId"][0]) == number:
                        with open(f"datatools/data/{number}.json",mode="w",encoding="utf-8") as f:
                            json.dump(data,f,indent=4)
                            f.close()
                            message += "scraped data!\n"
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

if __name__ == "__main__":
    processmaker(31500,31510,1)