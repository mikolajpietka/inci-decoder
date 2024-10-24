import os
import json
import re
import deepl

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
    authkeyfile = open("datatools/apikey.txt","r")
    authkey = authkeyfile.read()
    authkeyfile.close()
    translator = deepl.Translator(authkey)
    jsonfile = "datatools/rawdata.json"
    dir = "datatools/test/"
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
                    collected["refNo"] = data["substanceId"][0]
                    collected["inci"] = data["inciName"][0]
                    if len(data["casNo"]) != 0:
                        collected["casNo"] = data["casNo"][0]
                    else:
                        collected["casNo"] = "-"
                    if len(data["ecNo"]) != 0:
                        collected["ecNo"] = data["ecNo"][0]
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
                        collected["description"] = translator.translate_text(data["chemicalDescription"][0],target_lang="PL").text
                    else:
                        collected["description"] = ""

                    datatowrite[collected["inci"]] = collected
                of.close()
        keys = list(datatowrite.keys())
        keys.sort()
        sd = {i: datatowrite[i] for i in keys}
        json.dump(sd,jf,indent=2)
        jf.close()

# filecheck()
summjson()