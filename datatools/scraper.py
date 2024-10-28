import os
import json
import time
from multiprocessing import Process
from seleniumwire import webdriver
from seleniumwire.utils import decode

def webscrapjson(url,driver):
    driver.get(url)
    try: 
        driver.wait_for_request("api.tech.ec.europa.eu/search-api/prod/rest/search",timeout=10)
        time.sleep(0.5)
        data = []
        for request in driver.requests:
            if "https://api.tech.ec.europa.eu/search-api/prod/rest/search" in request.url:
                data.append(str(decode(request.response.body, request.response.headers.get('Content-Encoding', 'identity')), encoding="utf-8"))
        return data
    except Exception:
        return None

def rangescrapjson(fromno,tono):
    driver = webdriver.Chrome()
    driver.minimize_window()
    for number in range(fromno,tono+1):
        url = f"https://ec.europa.eu/growth/tools-databases/cosing/details/{number}"
        message = f"Scraping json data from reference number: {number}\n"
        scraped = webscrapjson(url,driver)
        if (scraped != None):
            for s in scraped:
                jsonS = json.loads(s)
                if len(jsonS["results"]) != 0:
                    if int(jsonS["results"][0]["metadata"]["substanceId"][0]) == number:
                        with open(f"datatools/data/{number}.json","w",encoding="utf-8") as f:
                            json.dump(jsonS,f,indent=4)
                            f.close()
                            message = message + "Scraped"
                else:
                    message = message + "Empty page"
        else:
            message = message + "Timeout"
        print(message)
        del driver.requests
    driver.quit()

def singleing(number):
    options = webdriver.ChromeOptions()
    options.add_argument("--log-level=3")
    options.add_experimental_option("excludeSwitches", ["enable-logging"])
    driver = webdriver.Chrome(options=options)
    driver.minimize_window()
    url = f"https://ec.europa.eu/growth/tools-databases/cosing/details/{number}"
    print(f"Scraping json data from reference number: {number}")
    scraped = webscrapjson(url,driver)
    if (scraped != None):
        data = json.loads(scraped[0])
        if (len(data["results"]) != 0):
            with open(f"datatools/data/{number}.json","w",encoding="utf-8") as f:
                json.dump(data,f,indent=4)
                f.close()
                print("Done!")
        else:
            print("Empty page")
    else:
        print("Timeout")
    del driver.requests
    driver.quit()

def imgscrap(url,driver):
    driver.get(url)
    try:
        driver.wait_for_request("api.tech.ec.europa.eu/cosing20/1.0/api/cosmetics/",timeout=10)
        for request in driver.requests:
            if ("api.tech.ec.europa.eu/cosing20/1.0/api/cosmetics" in request.url):
                if (request.response.status_code == 200):
                    return request.response.body
                else:
                    return False
    except Exception as error:
        return None

def imgscrapall():
    file = "datatools/rawdata.json"
    with open(file,"r",encoding="utf-8") as f:
        data = json.load(f)
        numlist = []
        for k in data:
            if data[k]["refNo"] > 36573:
                numlist.append(data[k]["refNo"])
        f.close()
    numlist.sort()
    if not os.path.exists("img/"):
        os.mkdir("img/")
    options = webdriver.ChromeOptions()
    options.add_argument("--log-level=3")
    options.add_experimental_option("excludeSwitches", ["enable-logging"])
    driver = webdriver.Chrome(options=options)
    driver.minimize_window()

    for number in numlist:
        print(f"Trying to get image from refNo: {number}")
        url = f"https://ec.europa.eu/growth/tools-databases/cosing/details/{number}"
        response = imgscrap(url,driver)
        if response != None:
            if response == False: 
                print("There is no image")
            else:
                with open(f"img/{number}.gif","wb") as f:
                    f.write(response)
                    f.close()
                    print("Got it!")
        else:
            print("Timeout!")
        del driver.requests
    driver.quit()
    print("Done!")


# rangescrapjson(27920, 106000) # All ingredients
# singleing(int(input("Enter reference number to scrap: ")))
# imgscrapall()

if __name__ == '__main__':
    start = time.time()
    # p1 = Process(target=rangescrapjson,args=(27900,47400))
    # p1.start()
    # p2 = Process(target=rangescrapjson,args=(47401,66900))
    # p2.start()
    # p3 = Process(target=rangescrapjson,args=(66901,86400))
    # p3.start()
    # p4 = Process(target=rangescrapjson,args=(86401,106000))
    # p4.start()
    imgscrapall()
    end = time.time()
    elapsed = (end - start)
    print(f"It took {elapsed} seconds")

