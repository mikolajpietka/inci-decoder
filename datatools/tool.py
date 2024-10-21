import os
import json

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
    jsonfile = "rawdata.json"
    dir = "test/"
    with open(jsonfile,"w",encoding="utf-8") as jf:
        datatowrite = {}
        i = 1
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
                    datatowrite[i] = collected
                    i += 1
                of.close()
        json.dump(datatowrite,jf,indent=2)
        jf.close()

# filecheck()
summjson()