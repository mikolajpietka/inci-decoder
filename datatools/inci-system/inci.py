import csv
import json

def lettersize(text: str) -> str:
    with open("../../replacetable.json","r",encoding="utf-8") as f:
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
    split = []
    for i in range(0,seplen):
        if i+1 < seplen:
            next = positions[i+1]
        else:
            next = len(text)
        if i == 0 and positions[0] != 0:
            split.append(text[0:positions[0]])
        split.append(text[positions[i]:positions[i]+1])
        if next - positions[i] != 1:
            split.append(text[positions[i]+1:next])
    if split == []:
        split.append(text)
    # Check for exceptions
    exceptions = {}
    for exc in rp["except"]:
        if exc in text:
            for tk in rp["except"][exc]:
                exceptions[tk] = rp["except"][exc][tk]
    # Make uppercase when needed
    newpart = []
    for part in split:
        partlen = len(part)
        if str(partlen) in rp.keys() and part in rp[str(partlen)].keys():
            if exceptions != {} and part in exceptions.keys():
                newpart.append(exceptions[part])
            else:
                newpart.append(rp[str(partlen)][part])
        else:
            newpart.append(part.capitalize())
    # Return corrected
    return "".join(newpart)

def lettersizeCSV():
    with open("systeminci.csv","r",encoding="utf-8") as f:
        incicsv = csv.DictReader(f)
        newinci = []
        for row in incicsv:
            print(f"Processing {row["INCI"]}...")
            # response = json.loads(requests.get("http://localhost/inci-decoder",params={"lettersize":row["INCI"]}).text) # until lettersize() not completed
            # row["INCI"] = response["converted"]
            row["INCI"] = lettersize(row["INCI"])
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
    lettersizeCSV()
    # print(lettersize("test/potem - hrch /ptfe, aqua, edta"))