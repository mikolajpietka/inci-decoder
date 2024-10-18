import time
import csv
from selenium import webdriver
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
        time.sleep(2)
    driver.quit()

rangescrap(105300, 106000)