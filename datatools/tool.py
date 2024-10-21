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

def addtocsv():
    csvfile = "rawdata.csv"
    with open(csvfile,"r+",encoding="utf-8") as cf:
        cf.close()
    for file in os.listdir("data"):
        file = "data/" + file
        with open(file,"r",encoding="utf-8") as of:
            data = json.load(of)

            of.close()
    # Work in progress
    print()

filecheck()
# addtocsv()