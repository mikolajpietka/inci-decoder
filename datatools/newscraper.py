import os
import json
import time
from multiprocessing import Process
from seleniumwire import webdriver
from seleniumwire.utils import decode

def scraper(driver, url):
    try:
        driver.get(url)
        driver.wait_for_request("api.tech.ec.europa.eu/cosing20/1.0/api/cosmetics",timeout=15)
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
    for number in list:
        url = f"https://ec.europa.eu/growth/tools-databases/cosing/details/{number}"
        response = scraper(driver,url)
        