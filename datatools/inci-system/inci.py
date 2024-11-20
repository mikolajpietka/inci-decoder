import csv
import requests
import json

def lettersize(text: str) -> str:
    with open("replacetable.json","r",encoding="utf-8") as f:
        rp = json.load(f)
        f.close()
    text = text.lower()
    separators = [",",".","-","+","(",")"," ","/","&",":","'","•",";","\\","|","[","]"]
    # Check what separators are included in checked text
    usedsep = []
    for sep in separators:
        if sep in text:
            usedsep.append(sep)
    # List positions of all separators
    positions = []
    if len(usedsep) != 0:
        for sep in usedsep:
            last = 0
            while text.find(sep,last) != -1:
                last = text.find(sep,last)
                positions.append(last)
                last += 1
    positions.sort()
    # Split string into words and serparators
    seplen = len(positions)
    

    processed = ""
    return processed

def lettersizeCSV():
    with open("systeminci.csv","r",encoding="utf-8") as f:
        incicsv = csv.DictReader(f)
        newinci = []
        for row in incicsv:
            print(f"Processing {row["INCI"]}...")
            response = json.loads(requests.get("http://localhost/inci-decoder",params={"lettersize":row["INCI"]}).text) # until lettersize() not completed
            row["INCI"] = response["converted"]
            newinci.append(row)
        f.close()
    with open("newinci.csv","w",encoding="utf-8",newline="") as f:
        header = list(newinci[0].keys())
        print(f"Header of csv file: {header}")
        writer = csv.DictWriter(f,header)
        writer.writeheader()
        writer.writerows(newinci)
        f.close()
    print("Done!")

if __name__ == "__main__":
    # lettersizeCSV()
    lettersize("test/potem - hrch /")