#!/usr/bin/python3

import argparse
import logging
import sys
import os

libDir = os.path.realpath(os.path.dirname(os.path.abspath(__file__)) + '/../lib')
sys.path.append(libDir)
from signalrcore.hub_connection_builder import HubConnectionBuilder

parser = argparse.ArgumentParser()
parser.add_argument("--token","-t", help="Your login token")
parser.add_argument("--serial","-s", help="Serial for product")
args = parser.parse_args()

if args.token is None:
    exit("Missing token, make sure u pass it in with --token or -t")

if args.serial is None:
    exit("Missing serial, make sure u pass it in with --serial or -s")

def get_access_token() -> str:
    return args.token

def product_update(stuff: list):
    print(f"received product update: {stuff}")

def charger_update(stuff: list):
    print(f"received charger update: {stuff}")

url = "https://api.easee.cloud/hubs/chargers"
options = {"access_token_factory": get_access_token}
connection = HubConnectionBuilder().with_url(url,options)\
            .configure_logging(logging.DEBUG)\
            .with_automatic_reconnect({
                "type": "raw",
                "keep_alive_interval": 10,
                "reconnect_interval": 5,
                "max_attempts": 5
            }).build()

def on_open():
    print("connection opened and handshake received ready to send messages")
    connection.send("SubscribeWithCurrentState", [args.serial, True])

def on_close():
    print("connection closed")

connection.on_open(lambda: on_open())
connection.on_close(lambda: on_close())

connection.on("ProductUpdate", product_update)
connection.on("ChargerUpdate", charger_update)

connection.start()

message = None
while message != "exit()":
    message = input(">> ")
    if message is not None and message != "" and message != "exit()":
        # connection.send("SendMessage", [username, message])
        continue

connection.stop()

sys.exit(0)

