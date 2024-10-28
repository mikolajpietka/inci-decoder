import os
import json
import re

def filecheck():
    for file in os.listdir("data"):
        file = "data/" + file
        if (os.path.getsize(file) != 0):
            print(f"Checking file: {file}")
            with open(file,"r",encoding="utf-8") as f:
                data = json.loads(f.read())
                f.close()
                if len(data["results"]) == 0:
                    os.remove(file)
                    print(f"Deleted {file}")
        else:
            os.remove(file)
            print("File deleted - empty")

def summjson():
    jsonfile = "datatools/rawdata.json"
    dir = "datatools/data/"
    with open(jsonfile,"w",encoding="utf-8") as jf:
        datatowrite = {}
        for file in os.listdir(dir):
            file = dir + file
            print(f"Getting info from: {file}")
            with open(file,"r",encoding="utf-8") as of:
                dataall = json.load(of)
                data = dataall["results"][0]["metadata"]
                if data["itemType"][0] == "ingredient":
                    collected = {}
                    collected["refNo"] = int(data["substanceId"][0])
                    collected["inci"] = data["inciName"][0]
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
                            rawtxt = re.sub(r"\([a-zA-Z0-9\s\;\:]+\)|\ ","",rawtxt)
                            rawtxt = rawtxt.replace("\r\n",", ")
                            collected["anx"] = rawtxt
                    else:
                        if len(data["annexNo"]) != 0 and len(data["refNo_digit"]) != 0:
                            collected["anx"] = f"{data["annexNo"][0]}/{data["refNo_digit"][0]}"
                        else:
                            collected["anx"] = ""
                    collected["function"] = data["functionName"]
                    if len(data["chemicalDescription"]) != 0:
                        collected["description"] = data["chemicalDescription"][0]
                    else:
                        collected["description"] = ""
                    collected["sccs"] = []
                    if len(data["sccsOpinionUrls"]) == len(data["sccsOpinion"]):
                        for x in range(len(data["sccsOpinionUrls"])):
                            collected["sccs"].append({})
                            collected["sccs"][x]["name"] = data["sccsOpinion"][x]
                            collected["sccs"][x]["url"] = data["sccsOpinionUrls"][x]
                    datatowrite[collected["inci"]] = collected
                of.close()
        keys = list(datatowrite.keys())
        keys.sort()
        sd = {i: datatowrite[i] for i in keys}
        json.dump(sd,jf,indent=4)
        jf.close()

def geninci():
    with open("datatools/rawdata.json","r",encoding="utf-8") as f:
        data = json.load(f)
        f.close()
        print("Read data from file")
        print("Checking...")
    for ing in data:
        if os.path.exists(f"datatools/img/{data[ing]["refNo"]}.gif"):
            data[ing]["img"] = True
        else:
            data[ing]["img"] = False
    with open("INCI.json","w",encoding="utf-8") as f:
        print("Adding all info into INCI.json")
        json.dump(data,f,indent=4)
        f.close()
    print("Done!")
# filecheck()
summjson()
# geninci()