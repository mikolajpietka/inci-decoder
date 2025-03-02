import os
import json
import re
import time
import requests
import shutil
from deep_translator import GoogleTranslator

def incijson():
    print("Starting to generate INCI.json file")
    start = time.time()
    datadir = "datatools/data/"
    output = "INCI.json"
    if os.path.exists(output):
        print("INCI.json already exists! To update info use another function")
        return False
    if os.path.exists(datadir) and len(os.listdir(datadir)) != 0:
        with open(output, "w", encoding="utf-8") as file:
            listed = os.listdir(datadir)
            datatowrite = {}
            for f in listed:
                print(f"Processing {f} ...")
                f = datadir + f
                with open(f, "r", encoding="utf-8") as datafile:
                    datawhole = json.load(datafile)
                    data = datawhole["results"][0]["metadata"]
                    if data["itemType"][0] == "ingredient":
                        mainkey = data["inciName"][0]
                        response = requests.get("http://localhost/inci-decoder/",params={"lettersize":mainkey})
                        resp = json.loads(response.text)
                        collected = {}
                        collected["inci"] = resp["converted"]
                        collected["refNo"] = int(data["substanceId"][0])
                        if len(data["casNo"]) != 0:
                            redacted = str(data["casNo"][0])
                            if "/" in redacted and " / " not in redacted:
                                redacted = redacted.replace("/"," / ").replace("  "," ")
                            if ";" in redacted:
                                redacted = redacted.replace(";"," / ").replace("  "," ")
                            collected["casNo"] = redacted
                        else:
                            collected["casNo"] = "-"
                        if len(data["ecNo"]) != 0:
                            redacted = str(data["ecNo"][0])
                            if "/" in redacted and " / " not in redacted:
                                redacted = redacted.replace("/"," / ").replace("  "," ")
                            if ";" in redacted:
                                redacted = redacted.replace(";"," / ").replace("  "," ")
                            collected["ecNo"] = redacted
                        else:
                            collected["ecNo"] = "-"
                        if len(data["cosmeticRestriction"]) != 0:
                            if "Please consider whether entry 419 of Annex II" in data["cosmeticRestriction"][0]:
                                collected["anx"] = "II/419 #Do oceny"
                            else:
                                rawtxt = str(data["cosmeticRestriction"][0])
                                rawtxt = re.sub(r"\([a-zA-Z0-9\s\;\:]+\)|  "," ",rawtxt)
                                rawtxt = rawtxt.replace("\r\n",", ")
                                collected["anx"] = rawtxt
                        else:
                            if len(data["annexNo"]) != 0 and len(data["refNo_digit"]) != 0:
                                collected["anx"] = f"{data["annexNo"][0]}/{data["refNo_digit"][0]}"
                            else:
                                collected["anx"] = ""
                        collected["function"] = data["functionName"]
                        collected["description"] = {}
                        if len(data["chemicalDescription"]) != 0:
                            collected["description"]["en"] = data["chemicalDescription"][0]
                            collected["description"]["pl"] = GoogleTranslator(source="en",target="pl").translate(data["chemicalDescription"][0])
                        collected["sccs"] = []
                        if len(data["sccsOpinionUrls"]) == len(data["sccsOpinion"]):
                            for x in range(len(data["sccsOpinionUrls"])):
                                collected["sccs"].append({})
                                collected["sccs"][x]["name"] = data["sccsOpinion"][x]
                                collected["sccs"][x]["url"] = data["sccsOpinionUrls"][x]
                        if os.path.exists(f"img/{collected["refNo"]}.gif"):
                            collected["gif"] = True
                        else:
                            collected["gif"] = False
                        datatowrite[mainkey] = collected
                    else:
                        print("Nah, it's a substance, next!")
                    datafile.close()
            keys = list(datatowrite.keys())
            keys.sort()
            sorted = {i: datatowrite[i] for i in keys}
            json.dump(sorted,file,indent=4)
            file.close()
    else:
        print("No data to convert")
    end = time.time()
    elapsed = round(end - start,2)
    print(f"Done! It took {elapsed} seconds")

def updateinci():
    print("Update tool for INCI.json")
    incifile = "INCI.json"
    incibackup = "INCI-backup.json"
    if not os.path.exists(incifile):
        print("INCI.json file does not exist, create it using incijson()")
        return None
    print("What data you want to update INCI.json with: \n1. Data from CosIng \n2. Data from Cosmile")
    match int(input("Choose number: ")):
        case 1:
            print("Making backup of INCI.json")
            shutil.copy(incifile,incibackup)
            datafiles = "datatools/data"
            if not os.path.exists(datafiles):
                print(f"Directory {datafiles} does not exist")
                return None
            else:
                print("This function is not completed...")
                return None
                with open(incifile,"r",encoding="utf-8") as of:
                    oldinci = json.load(of)
                    of.close()
                datalist = os.listdir(datafiles)
                for datafile in datalist:
                    dir = datafiles + datafile
                    with open(dir,"r",encoding="utf-8") as df:
                        data = json.load(df)["results"][0]["metadata"]
                        df.close()
                    if data["inciName"][0] in oldinci:
                        print("Exists")
                    else:
                        print(f"Ingredient {data["inciName"][0]} is not in database")
                        input("Press Enter to continue...")
        case 2:
            cosmilefile = "datatools/cosmile.json"
            print("Making backup of INCI.json")
            shutil.copy(incifile,incibackup)
            print("Getting data into buffer")
            with open(incifile,"r",encoding="utf-8") as of:
                incidata = json.load(of)
                of.close()
            with open(cosmilefile,"r",encoding="utf-8") as of:
                cosmiledata = json.load(of)
                of.close()
            print("Updating data with Cosmile data")
            for ingredient in incidata:
                if ingredient in cosmiledata:
                    incidata[ingredient]["cosmile"] = cosmiledata[ingredient]
                else:
                    incidata[ingredient]["cosmile"] = None
            print("Sorting data (to be sure it's sorted)")
            keys = list(incidata.keys())
            keys.sort()
            sorted = {i: incidata[i] for i in keys}
            print("Saving data into INCI.json")
            with open(incifile,"w",encoding="utf-8") as of:
                json.dump(sorted,of,indent=4)
                of.close()
            print("Done!")
        case _:
            print("Wrong number!")
            return None

if __name__ == '__main__':
    # incijson()
    updateinci()