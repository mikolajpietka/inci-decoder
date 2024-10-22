import json
from seleniumwire import webdriver
from seleniumwire.utils import decode

def webscrapjson(url,driver):
    driver.get(url)
    try: 
        driver.wait_for_request("api.tech.ec.europa.eu/search-api/prod/rest/search",timeout=10)
        data = []
        for request in driver.requests:
            if (request.url == "https://api.tech.ec.europa.eu/search-api/prod/rest/search?apiKey=285a77fd-1257-4271-8507-f0c6b2961203&text=*&pageSize=100&pageNumber=1"):
                data.append(str(decode(request.response.body, request.response.headers.get('Content-Encoding', 'identity')), encoding="utf-8"))
        return data
    except Exception:
        return None

def rangescrapjson(fromno,tono):
    driver = webdriver.Chrome()
    driver.minimize_window()
    for number in range(fromno,tono+1):
        url = f"https://ec.europa.eu/growth/tools-databases/cosing/details/{number}"
        print(f"Scraping json data from reference number: {number}")
        scraped = webscrapjson(url,driver)
        if (scraped != None):
            data = json.loads(scraped[0])
            if (len(data["results"]) != 0):
                with open(f"datatools/data/{number}.json","w",encoding="utf-8") as f:
                    json.dump(data,f,indent=4)
                    f.close()
            else:
                print("Empty page")
        else:
            print("Timeout")
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
# rangescrapjson(27920, 106000) # All ingredients
singleing(int(input("Enter reference number to scrap: ")))