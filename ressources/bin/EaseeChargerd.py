# vim: tabstop=4 autoindent expandtab
# This file is part of Jeedom.
#
# Jeedom is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Jeedom is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Jeedom. If not, see <http://www.gnu.org/licenses/>.

import os
import sys
import traceback
import json
import argparse
import datetime

libDir = os.path.realpath(os.path.dirname(os.path.abspath(__file__)) + "/../lib")
sys.path.append(libDir)

from logfilter import *
from jeedom import *
from account import Account
from charger import Charger

logger = logging.getLogger("EaseeChargerd")
logger.addFilter(logFilter())
logLevel = "error"
callback = ""
apiKey = ""
pidFile = "/tmp/jeedom/EaseeCharger/daemon.pid"
socketPort = -1
socketHost = "localhost"
startTime = datetime.datetime.fromtimestamp(time.time())

_commands = {
    "registerAccount": [
        "accountId",
        "accountName",
        "accessToken",
        "expiresIn",
        "expiresAt",
    ],
    "newToken": [
        "accountId",
        "accountName",
        "accessToken",
        "expiresIn",
        "expiresAt",
    ],
    "startCharger": [
        "id",
        "serial",
        "name",
        "accountId",
    ],
    "stopCharger": [
        "id",
    ],
    "shutdown": [],
}


# ===============================================================================
# options
# ...............................................................................
# Prise en compte des options de la ligne de commande
# ===============================================================================
def options():
    global logLevel
    global callback
    global apiKey
    global pidFile
    global socketPort

    parser = argparse.ArgumentParser(
        description="EaseeCharger daemon for Jeedom's plugin"
    )
    parser.add_argument("-l", "--loglevel", help="Log level for the daemon", type=str)
    parser.add_argument("-c", "--callback", help="Callback", type=str)
    parser.add_argument("-a", "--apikey", help="ApiKey", type=str)
    parser.add_argument("-p", "--pid", help="Pif file", type=str)
    parser.add_argument(
        "-s", "--socketport", help="Port to receive plugin's message", type=int
    )
    parser.add_argument("-x", "--secureLog", help="Securised logs", action="store_true")
    args = parser.parse_args()

    if args.loglevel:
        if args.loglevel == "extendedDebug":
            logLevel = "debug"
            logFilter.set_quietDebug(False)
        else:
            logLevel = args.loglevel
            logFilter.set_quietDebug(True)
    if args.callback:
        callback = args.callback
    if args.apikey:
        apiKey = args.apikey
        logFilter.add_sensible(apiKey)
    if args.pid:
        pidFile = args.pid
    if args.socketport:
        socketPort = int(args.socketport)
    logFilter.set_secure(args.secureLog)

    jeedom_utils.set_logLevel(logLevel)

    logger.info("┌── Start daemon  ──────────────────────────────────────────")
    logger.info("│ Log level: " + logLevel)
    if logLevel == "debug":
        logger.info("│ extendedDebug: " + str(not logFilter.get_quietDebug()))
    logger.info("│ callback: " + callback)
    logger.info("│ Apikey: " + apiKey)
    logger.info("│ Socket Port: " + str(socketPort))
    logger.info("│ Socket Host: " + socketHost)
    logger.info("│ PID file: " + pidFile)
    logger.info("└───────────────────────────────────────────────────────────")


# ===============================================================================
# logStatus
# ...............................................................................
# Log l'état interne du daemon
# ===============================================================================
def logStatus():
    logger.info("┌── Daemon state: ──────────────────────────────────────────")
    logger.info("│ Daemon:")
    logger.info(f"│   - Started at : {startTime}")
    logger.info("│ Accounts:")
    for account in Account.all():
        expiresAt = datetime.datetime.fromtimestamp(account.getExpiresAt())
        lifetime = account.getLifetime()
        time2renew = datetime.datetime.fromtimestamp(account.getTime2renew())
        logger.info(f"│ - {account.getName()} ({account.getId()})")
        logger.info(f"│   - Token expires at {expiresAt}")
        logger.info(f"│   - Lifetime: {lifetime}")
        logger.info(f"│   - Time to renew: {time2renew}")
    logger.info("│ Chargers:")
    for charger in Charger.all():
        logger.info(f"│ - {charger.getName()}")
        logger.info(f"│   - Serial:          {charger.getSerial()}")
        accountName = charger.getAccount().getName()
        logger.info(f"│   - Account:         {accountName}")
        logger.info(f"│   - State:           {charger.getState()}")
        logger.info(f"│   - Cloud Connected: {charger.is_running()}")
        logger.info(
            f"│   - Nb Restart:      signalr: {charger.getNbSignalrRestart()}   watcher: {charger.getNbWatcherRestart()}"
        )
    logger.info("└──────────────────────────────────────────────────────────")


# ===============================================================================
# register_account
# ...............................................................................
# Ajout d'un account
# ===============================================================================
def register_account(id, name, accessToken, expiresAt, expiresIn):
    account = Account.byId(id)
    if account:
        logger.debug(f"Account < {id} > is already defined")
        return
    logger.info(f"Starting account {name} (id:  {id})")
    account = Account(id, name, accessToken, expiresAt, expiresIn)
    if account:
        jeedom_com.send_change_immediate(
            {"object": "account", "message": "started", "accountId": account.getId()}
        )


# ===============================================================================
# newToken
# ...............................................................................
# Enregistrement d'un nouveau token
# ===============================================================================
def newToken(id, name, accessToken, expiresAt, expiresIn):
    account = Account.byId(id)
    if not account:
        logger.debug(f"Account < {id} > not found")
        return
    account.setName(name)
    account.setAccessToken(accessToken)
    account.setExpiresAt(expiresAt)
    account.setExpiresIn(expiresIn)


# ===============================================================================
# start_charger
# ...............................................................................
# Démarrage d'un thread pour un charger
# ===============================================================================
def start_charger(id, name, serial, accountId):
    charger = Charger.byId(id)
    if charger:
        logger.debug(f"Charger {id} is already running")
        return
    logger.info(f"Starting charger {name} (id: {id}) ")
    account = Account.byId(accountId)
    charger = Charger(id, name, serial, account)
    charger.run()


# ===============================================================================
# stop_charger
# ...............................................................................
# Arrêt du thread d'un charger
# ===============================================================================
def stop_charger(id):
    charger = Charger.byId(id)
    if charger:
        charger.stop()


# ===============================================================================
# read_socket
# ...............................................................................
# Traitement des message reçu de Jeedom par jeedom_com et placé dans la
# queue JEEDOM_SOCKET_MESSAGE
# ===============================================================================
def read_socket():
    global JEEDOM_SOCKET_MESSAGE

    if not JEEDOM_SOCKET_MESSAGE.empty():
        # jeedom_com a reçu un message qu'il a mis en queue. On le récupère ici
        message = json.loads(JEEDOM_SOCKET_MESSAGE.get().decode())
        logger.info(f"Message received from Jeedom: {message}")
        if 'apikey' not in message or message['apikey'] != apiKey:
            logger.error("La clé api in invalide")
            return

        if "cmd" not in message:
            logger.warning("'cmd' is not in message")
            return

        if message["cmd"] not in _commands:
            logger.warning(f"Unknow command {message['cmd']} in message")
            return

        for arg in _commands[message["cmd"]]:
            if arg not in message:
                logger.error(f"Arg {arg} is missing for cmd {message['cmd']}")
                return

        if message["cmd"] == "registerAccount":
            logFilter.add_sensible(message["accessToken"])
            register_account(
                message["accountId"],
                message["accountName"],
                message["accessToken"],
                message["expiresAt"],
                message["expiresIn"],
            )
        elif message["cmd"] == "newToken":
            newToken(
                message["accountId"],
                message["accountName"],
                message["accessToken"],
                message["expiresAt"],
                message["expiresIn"],
            )
        elif message["cmd"] == "unregisterAccount":
            unregister_account(message["accountId"])
        elif message["cmd"] == "startCharger":
            start_charger(
                message["id"], message["name"], message["serial"], message["accountId"]
            )
        elif message["cmd"] == "stopCharger":
            stop_charger(message["id"])
        elif message["cmd"] == "shutdown":
            shutdown()
        return


# ===============================================================================
# handler
# ...............................................................................
# Procédure appelée pour trapper divers signaux
# ===============================================================================
def handler(signum=None, frame=None):

    if signum == signal.SIGUSR1:
        logStatus()
        return
    if signum == signal.SIGALRM:
        signal.alarm(3600)
        logger.debug("ALARM")
        logStatus()
        return
    logger.debug("Signal %i caught, exiting..." % int(signum))
    shutdown()


# ===============================================================================
# shutdown
# ...............................................................................
# Procédure d'arrêt du daemon
# ===============================================================================
def shutdown():
    logger.debug("Shutdown...")
    try:
        jeedom_socket.close()
    except:
        pass
    for charger in list(Charger.all()):
        charger.stop()
    logger.debug("Removing PID file " + pidFile)
    try:
        os.remove(pidFile)
    except:
        pass
    logger.debug("Exit 0")
    sys.stdout.flush()
    os._exit(0)


###############################
#                             #
#  #    #    ##    #  #    #  #
#  ##  ##   #  #   #  ##   #  #
#  # ## #  #    #  #  # #  #  #
#  #    #  ######  #  #  # #  #
#  #    #  #    #  #  #   ##  #
#  #    #  #    #  #  #    #  #
#                             #
###############################


options()

signal.signal(signal.SIGINT, handler)
signal.signal(signal.SIGUSR1, handler)
signal.signal(signal.SIGALRM, handler)

try:
    jeedom_utils.write_pid(pidFile)

    # Configuration du canal pour l'envoi de messages a Jeedom
    jeedom_com = jeedom_com(apikey=apiKey, url=callback)
    if not jeedom_com.test():
        logger.error("Network communication issue. Unable to send messages to Jeedom")
        shutdown()
    Charger.set_jeedom_com(jeedom_com)

    # Réception des message de jeedom qui seont mis en queue dans JEEDOM_SOCKET_MESSAGE
    jeedom_socket = jeedom_socket(port=socketPort, address=socketHost)
    jeedom_socket.open()

    signal.alarm(10)

    # Annonce à jeedom que le daemon est démarré
    jeedom_com.send_change_immediate({"object": "daemon", "message": "started"})

    # Boucle de traitement des messages mis en queue par jeedom_socket
    try:
        while 1:
            time.sleep(0.5)
            read_socket()
    except KeyboardInterrupt:
        shutdown()

except Exception as e:
    logger.error("Fatal error: " + str(e))
    logger.info(traceback.format_exc())
    shutdown()
