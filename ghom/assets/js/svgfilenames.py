from collections import defaultdict
zones = {
    "regions": {
        "Atieh": {
            "label": "بلوک A- رس",
            "contractor": "شرکت ساختمانی رس",
            "block": "A",
            "zones": [
                {
                    "label": "زون 1 (رس)",
                    "svgFile": "Zone01AT.svg"
                },
                {
                    "label": "زون 2 (رس)",
                    "svgFile": "Zone02AT.svg"
                },
                {
                    "label": "زون 3 (رس)",
                    "svgFile": "Zone03AT.svg"
                },
                {
                    "label": "زون 4 (رس)",
                    "svgFile": "Zone04AT.svg"
                },
                {
                    "label": "زون 5 (رس)",
                    "svgFile": "Zone05AT.svg"
                },
                {
                    "label": "زون 6 (رس)",
                    "svgFile": "Zone06AT.svg"
                },
                {
                    "label": "زون 7 (رس)",
                    "svgFile": "Zone07AT.svg"
                },
                {
                    "label": "زون 8 (رس)",
                    "svgFile": "Zone08AT.svg"
                },
                {
                    "label": "زون 9 (رس)",
                    "svgFile": "Zone09AT.svg"
                },
                {
                    "label": "زون 10 (رس)",
                    "svgFile": "Zone10AT.svg"
                },
                {
                    "label": "زون 11 (رس)",
                    "svgFile": "Zone11AT.svg"
                },
                {
                    "label": "زون 12 (رس)",
                    "svgFile": "Zone12AT.svg"
                },
                {
                    "label": "زون 13 (رس)",
                    "svgFile": "Zone13AT.svg"
                },
                {
                    "label": "زون 14 (رس)",
                    "svgFile": "Zone14AT.svg"
                },
                {
                    "label": "زون 15 (رس)",
                    "svgFile": "Zone15AT.svg"
                },
                {
                    "label": "زون 16 (رس)",
                    "svgFile": "Zone16AT.svg"
                },
                {
                    "label": "زون 17 (رس)",
                    "svgFile": "Zone17AT.svg"
                },
                {
                    "label": "زون 18 (رس)",
                    "svgFile": "Zone18AT.svg"
                },
                {
                    "label": "زون 19 (رس)",
                    "svgFile": "Zone19AT.svg"
                }
            ]
        },
        "org": {
            "label": "بلوک - اورژانس A- رس",
            "contractor": "شرکت ساختمانی رس",
            "block": "A - اورژانس",
            "zones": [
                {
                    "label": "زون اورژانس غربی ",
                    "svgFile": "ZoneEmergencyWestAT.svg"
                },
                {
                    "label": "زون اورژانس شمالی ",
                    "svgFile": "ZoneEmergencyNorthAT.svg"
                },
                {
                    "label": "زون اورژانس جنوبی ",
                    "svgFile": "ZoneEmergencySouthAT.svg"
                }
            ]
        },
        "rosB": {
            "label": "بلوک B-رس",
            "contractor": "شرکت ساختمانی رس",
            "block": "B",
            "zones": [
                {
                    "label": "زون 1 (رس B)",
                    "svgFile": "Zone01ARJ.svg"
                },
                {
                    "label": "زون 2 (رس B)",
                    "svgFile": "Zone02ARJ.svg"
                },
                {
                    "label": "زون 3 (رس B)",
                    "svgFile": "Zone03ARJ.svg"
                },
                {
                    "label": "زون 11 (رس B)",
                    "svgFile": "Zone11ARJ.svg"
                },
                {
                    "label": "زون 12 (رس B)",
                    "svgFile": "Zone12ARJ.svg"
                },
                {
                    "label": "زون 13 (رس B)",
                    "svgFile": "Zone13ARJ.svg"
                },
                {
                    "label": "زون 14 (رس B)",
                    "svgFile": "Zone14ARJ.svg"
                },
                {
                    "label": "زون 19 (رس B)",
                    "svgFile": "Zone19ARJ.svg"
                },
                {
                    "label": "زون 20 (رس B)",
                    "svgFile": "Zone20ARJ.svg"
                },
                {
                    "label": "زون 21 (رس B)",
                    "svgFile": "Zone21ARJ.svg"
                }
            ]
        },
        "rosC": {
            "label": "بلوک C-عمران آذرستان",
            "contractor": "شرکت ساختمانی رس",
            "block": "C",
            "zones": [
                {
                    "label": "زون 4 (C عمران آذرستان)",
                    "svgFile": "Zone04ARJ.svg"
                },
                {
                    "label": "زون 5 (C عمران آذرستان)",
                    "svgFile": "Zone05ARJ.svg"
                },
                {
                    "label": "زون 6 (C عمران آذرستان)",
                    "svgFile": "Zone06ARJ.svg"
                },
                {
                    "label": "زون 7E (C عمران آذرستان)",
                    "svgFile": "Zone07EARJ.svg"
                },
                {
                    "label": "زون 7S (C عمران آذرستان)",
                    "svgFile": "Zone07SARJ.svg"
                },
                {
                    "label": "زون 7N (C عمران آذرستان)",
                    "svgFile": "Zone07NARJ.svg"
                },
                {
                    "label": "زون 8 (C عمران آذرستان)",
                    "svgFile": "Zone08ARJ.svg"
                },
                {
                    "label": "زون 9 (C عمران آذرستان)",
                    "svgFile": "Zone09ARJ.svg"
                },
                {
                    "label": "زون 10 (C عمران آذرستان)",
                    "svgFile": "Zone10ARJ.svg"
                },
                {
                    "label": "زون 22 (C عمران آذرستان)",
                    "svgFile": "Zone22ARJ.svg"
                },
                {
                    "label": "زون 23 (C عمران آذرستان)",
                    "svgFile": "Zone23ARJ.svg"
                },
                {
                    "label": "زون 24 (C عمران آذرستان)",
                    "svgFile": "Zone24ARJ.svg"
                }
            ]
        },
        "hayatOmran": {
            "label": "حیاط عمران آذرستان",
            "contractor": "شرکت عمران آذرستان",
            "block": "حیاط",
            "zones": [
                {
                    "label": "زون 15 حیاط عمران آذرستان",
                    "svgFile": "Zone15ARJ.svg"
                },
                {
                    "label": "زون 16 حیاط عمران آذرستان",
                    "svgFile": "Zone16ARJ.svg"
                },
                {
                    "label": "زون 17 حیاط عمران آذرستان",
                    "svgFile": "Zone17ARJ.svg"
                },
                {
                    "label": "زون 18 حیاط عمران آذرستان",
                    "svgFile": "Zone18ARJ.svg"
                }
            ]
        },
        "hayatRos": {
            "label": "حیاط رس",
            "contractor": "شرکت ساختمانی رس",
            "block": "حیاط",
            "zones": [
                {
                    "label": "زون 1 حیاط رس ",
                    "svgFile": "Zone1ROS.svg"
                },
                {
                    "label": "زون 2 حیاط رس ",
                    "svgFile": "Zone2ROS.svg"
                },
                {
                    "label": "زون 3 حیاط رس ",
                    "svgFile": "Zone3ROS.svg"
                },
                {
                    "label": "زون 11 حیاط رس ",
                    "svgFile": "Zone11ROS.svg"
                },
                {
                    "label": "زون 12 حیاط رس",
                    "svgFile": "Zone12ROS.svg"
                },
                {
                    "label": "زون 13 حیاط رس",
                    "svgFile": "Zone13ROS.svg"
                },
                {
                    "label": "زون 14 حیاط رس",
                    "svgFile": "Zone14ROS.svg"
                },
                {
                    "label": "زون 16 حیاط رس",
                    "svgFile": "Zone16ROS.svg"
                },
                {
                    "label": "زون 19 حیاط رس",
                    "svgFile": "Zone19ROS.svg"
                },
                {
                    "label": "زون 20 حیاط رس",
                    "svgFile": "Zone20ROS.svg"
                },
                {
                    "label": "زون 21 حیاط رس",
                    "svgFile": "Zone21ROS.svg"
                },
                {
                    "label": "زون 25 حیاط رس",
                    "svgFile": "Zone25ROS.svg"
                },
                {
                    "label": "زون 26 حیاط رس",
                    "svgFile": "Zone26ROS.svg"
                }
            ]
        }
    }
}


result = defaultdict(set)

for region in zones["regions"].values():
    contractor = region["contractor"]
    for z in region["zones"]:
        result[contractor].add(z["svgFile"])

for contractor, svgs in result.items():
    for svg in sorted(svgs):
        print(f" {contractor} - {svg}")

output = {
    contractor: svgs
    for contractor, svgs in result.items()
}
