import time
import csv
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

# Function to scrape the content from each page
def scrape_page(driver, url):
    driver.get(url)
    
    try:
        # Wait for the table to appear (adjust the waiting time as necessary)
        WebDriverWait(driver, 10).until(
            EC.presence_of_element_located((By.TAG_NAME, "table"))
        )
        
        # Once the table is present, extract the data
        table = driver.find_element(By.TAG_NAME, "table")
        rows = table.find_elements(By.TAG_NAME, "tr")
        
        # Extract data from the second column onward and remove newlines
        page_data = []
        for row in rows:
            cols = row.find_elements(By.TAG_NAME, "td")
            # Skip the first column, and store only from the second column onward
            clean_data = [col.text.replace("\n", " | ") for col in cols[1:]]  # cols[1:] skips the first column
            page_data.extend(clean_data)  # Flattening the list
        
        return page_data
        
    except Exception as e:
        print(f"Error scraping {url}: {e}")
        return None

# Function to save data into a CSV file
def save_to_csv(data, filename, number):
    with open(filename, mode='a', newline='', encoding='utf-8') as file:  # Append mode
        writer = csv.writer(file)
        # Add {number} at the end of the data
        writer.writerow(data + "," + str(number))  # Append number to the data row

# Main function to iterate through the range of URLs
def scrape_range(start, end, output_filename):
    # Setup the webdriver (you may need to specify the path to your webdriver)
    driver = webdriver.Chrome()

    for number in range(start, end + 1):
        url = f"https://ec.europa.eu/growth/tools-databases/cosing/details/{number}"
        print(f"Scraping URL: {url}")
        
        page_data_raw = scrape_page(driver, url)
        page_data = ",".join(f'"{w}' for w in page_data_raw)
        
        if page_data:
            save_to_csv(page_data, output_filename, number)  # Save flattened data with {number} at the end
        
        # Pause between requests to avoid being flagged as a bot
        time.sleep(2)  # Adjust the sleep time as needed

    # Close the driver
    driver.quit()

# Call the function to scrape the range and save the result
scrape_range(105074, 105075, 'scraped_data.csv')
