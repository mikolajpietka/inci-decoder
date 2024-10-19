import time
import csv
from seleniumwire import webdriver
from seleniumwire.utils import decode
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

def webscrap(url,driver):
    driver.get(url)

    try:
        WebDriverWait(driver,7).until(
            EC.presence_of_element_located((By.TAG_NAME, "table"))
        )
        table = driver.find_element(By.TAG_NAME, "table")
        rows = table.find_elements(By.TAG_NAME, "tr")

        if (len(rows) > 8):
            return None

        table_data = []
        for row in rows:
            cols = row.find_elements(By.TAG_NAME,"td")
            clean_data = cols[1].text.replace("\n"," | ")
            table_data.append(clean_data)
        
        return table_data

    except Exception as error:
        # print(error)
        return None

def rangescrap(fromno,tono):
    driver = webdriver.Chrome()
    for number in range(fromno,tono+1):
        url = f"https://ec.europa.eu/growth/tools-databases/cosing/details/{number}"
        print(f"Scraping data from reference number: {number}")
        scraped = webscrap(url,driver)
        if (scraped != None):
            scraped.append(number)
            with open("scraped.csv", mode='a', newline='', encoding='utf-8') as file:  # Append mode
                writer = csv.writer(file,dialect='excel')
                writer.writerow(scraped)
        else:
            print("Empty page")
        time.sleep(1)
    driver.quit()

def webscrapjson(url,driver):
    driver.get(url)
    try: 
        WebDriverWait(driver,3).until(
            EC.presence_of_element_located((By.TAG_NAME, "table"))
        )
        data = []
        for request in driver.requests:
            if (request.url == "https://api.tech.ec.europa.eu/search-api/prod/rest/search?apiKey=285a77fd-1257-4271-8507-f0c6b2961203&text=*&pageSize=100&pageNumber=1"):
                data.append(str(decode(request.response.body, request.response.headers.get('Content-Encoding', 'identity')), encoding="utf-8"))
        return data
    except Exception as error:
        return None

def rangescrapjson(fromno,tono):
    options = webdriver.ChromeOptions()
    # options.add_experimental_option(name="detach",value=True)
    options.add_argument("--start-maximized")
    driver = webdriver.Chrome(options=options)
    for number in range(fromno,tono+1):
        url = f"https://ec.europa.eu/growth/tools-databases/cosing/details/{number}"
        print(f"Scraping json data from reference number: {number}")
        scraped = webscrapjson(url,driver)
        if (scraped != None):
            f = open(f"data/{number}.json","w")
            f.write(scraped[0])
            f.close()
        else:
            print("Empty page")
        del driver.requests
        time.sleep(1)
    driver.quit()

# rangescrap(27920, 106000)
# webscrapjson("https://ec.europa.eu/growth/tools-databases/cosing/details/27928",webdriver.Chrome())
rangescrapjson(28100,40000)