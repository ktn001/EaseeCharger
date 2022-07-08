#!/usr/bin/python3
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
from jeedom import *
from account import Account
from charger import Charger
from datetime import datetime

_logLevel = 'error'
_extendedDebug = False
_callback = ''
_apiKey = ''
_pidFile = '/tmp/jeedom/EaseeCharger/daemon.pid'
_socketPort = -1
_socketHost = 'localhost'
_secureLog = False

_commands = {
    'startAccount' : [
        'account',
        'accessToken',
        'expiresIn',
        'expiresAt',
    ],
    'stopAccount' : [
        'account',
    ],
    'startCharger' : [
        'id',
        'serial',
        'name',
        'account',
    ],
    'stopCharger' : [
        'id',
    ],
    'shutdown' : [],
}
#===============================================================================
# options
#...............................................................................
# Prise en compte des options de la ligne de commande
#===============================================================================
def options():
    global _logLevel
    global _extendedDebug
    global _callback
    global _apiKey
    global _pidFile
    global _socketPort
    global _secureLog

    parser = argparse.ArgumentParser( description="EaseeCharger daemon for Jeedom's plugin")
    parser.add_argument("-l", "--loglevel", help="Log level for the daemon", type=str)
    parser.add_argument("-c", "--callback", help="Callback", type=str)
    parser.add_argument("-a", "--apikey", help="ApiKey", type=str)
    parser.add_argument("-p", "--pid", help="Pif file", type=str)
    parser.add_argument("-s", "--socketport", help="Port to receive plugin's message", type=int)
    parser.add_argument("-x", "--secureLog", help="Securised logs", action='store_true')
    args = parser.parse_args()

    if args.loglevel:
        if args.loglevel == 'extendedDebug':
            _logLevel = 'debug'
            _extendedDebug = True
        else:
            _logLevel = args.loglevel
            _extendedDebug = False
    if args.callback:
        _callback = args.callback
    if args.apikey:
        _apiKey = args.apikey
    if args.pid:
        _pidFile = args.pid
    if args.socketport:
        _socketPort = int(args.socketport)
    _secureLog = args.secureLog

    jeedom_utils.set_logLevel(_logLevel, _extendedDebug)
    
    logging.info('Start daemon')
    logging.info('Log level: ' + _logLevel)
    if _logLevel == 'debug':
        logging.info('extendedDebug: ' + str(_extendedDebug))
    logging.info('callback: ' + _callback)
    if _secureLog:
        logging.debug('Apikey: **********')
    else:
        logging.debug('Apikey: ' + _apiKey)
    logging.info('Socket Port: ' + str(_socketPort))
    logging.info('Socket Host: ' + _socketHost)
    logging.info('PID file: ' + _pidFile)

#===============================================================================
# logStatus
#...............................................................................
# Log l'état interne du daemon
#===============================================================================
def logStatus():
    logging.info ("┌── Daemon state: ──────────────────────────────────────────")
    logging.info ("│ Accounts:") 
    for account in Account.all():
        expiresAt = datetime.fromtimestamp(account.getExpiresAt())
        lifetime = account.getLifetime()
        time2renew = datetime.fromtimestamp(account.getTime2renew())
        logging.info (f"│ - {account.getName()}")
        logging.info (f"│   - Token expires at {expiresAt}")
        logging.info (f"│   - Lifetime: {lifetime}")
        logging.info (f"│   - Time to renew: {time2renew}")
    logging.info ("│ Chargers:") 
    for charger in Charger.all():
        logging.info (f"│ - {charger.getName()}")
        logging.info (f"│   - Serial:     {charger.getSerial()}")
        accountName = charger.getAccount().getName()
        logging.info (f"│   - Account:    {accountName}")
        logging.info (f"│   - State:      {charger.getState()}")
        logging.info (f"│   - Nb Restart: {charger.getNbRestart()}")
    logging.info ("└──────────────────────────────────────────────────────────")


#===============================================================================
# start_account
#...............................................................................
# Ajout d'un account
#===============================================================================
def start_account(name, accessToken, expiresAt, expiresIn):
    account = Account.byName(name)
    if account:
        logging.warning(f"Account < {name} > is already defined")
        return
    logging.info(f"Starting account < {name} >")
    account = Account(name, accessToken, expiresAt, expiresIn)
    if account:
        jeedom_com.send_change_immediate({
            'object' : 'account',
            'message': 'started',
            'account' : account.getName()
    })


#===============================================================================
# stop_account
#...............................................................................
# Retrait d'un account
#===============================================================================
def stop_account(name):
    account = Account.byName(name)
    if account:
        account.remove()

#===============================================================================
# start_charger
#...............................................................................
# Démarrage d'un thread pour un charger
#===============================================================================
def start_charger(id, name, serial, accountName):
    charger = Charger.byId(id)
    if charger:
        logging.warning(f"Charger {id} is already running")
        return
    logging.info(f"Starting charger {name} (id: {id}) ")
    account = Account.byName(accountName)
    charger = Charger(id, name, serial, account)
    charger.run()

#===============================================================================
# stop_charger
#...............................................................................
# Arrêt du thread d'un charger
#===============================================================================
def stop_charger(id):
    charger = Charger.byId(id)
    if charger:
        charger.remove()

#===============================================================================
# read_socket
#...............................................................................
# Traitement des message reçu de Jeedom par jeedom_com et placé dans la
# queue JEEDOM_SOCKET_MESSAGE
#===============================================================================
def read_socket():
    global JEEDOM_SOCKET_MESSAGE

    if not JEEDOM_SOCKET_MESSAGE.empty():
        # jeedom_com a reçu un message qu'il a mis en queue. On le récupère ici
        message = json.loads(JEEDOM_SOCKET_MESSAGE.get().decode())
        message2log = dict(message)
        if _secureLog:
            if 'accessToken' in message2log.keys():
                message2log['accessToken'] = '**********'
            if 'apikey' in message2log.keys():
                message2log['apikey'] = '**********'
        logging.info(f"Message received from Jeedom: {message2log}")

        if 'cmd' not in message:
            logging.warning("'cmd' is not in message")
            return

        if message['cmd'] not in _commands:
            logging.warning(f"Unknow command {message['cmd']} in message")
            return

        for arg in _commands[message['cmd']]:
            if arg not in message:
                logging.error(f"Arg {arg} is missing for cmd {message['cmd']}")
                return

        if message['cmd'] == 'startAccount':
            start_account(message['account'],message['accessToken'],message['expiresAt'],message['expiresIn'])
        elif message['cmd'] == 'stopAccount':
            stop_account(message['account'])
        elif message['cmd'] == 'startCharger':
            start_charger(message['id'],message['name'],message['serial'],message['account'])
        elif message['cmd'] == 'stopCharger':
            stop_charger(message['id'])
        elif message['cmd'] == 'shutdown':
            shutdown()
        return

#===============================================================================
# handler
#...............................................................................
# Procédure appelée pour trapper divers signaux
#===============================================================================
def handler(signum=None, frame=None):

    if signum == signal.SIGUSR1:
        logStatus()
        return
    if signum == signal.SIGALRM:
        logging.debug("ALARM")
        signal.alarm(30)
        return
    logging.debug("Signal %i caught, exiting..." % int(signum))
    shutdown()

#===============================================================================
# shutdown
#...............................................................................
# Procédure d'arrêt du daemon
#===============================================================================
def shutdown():
    logging.debug("Shutdown...")
    try:
        jeedom_socket.close()
    except:
        pass
    for charger in list(Charger.all()):
        charger.remove()
    logging.debug("Removing PID file " + _pidFile)
    try:
        os.remove(_pidFile)
    except:
        pass
    logging.debug("Exit 0")
    sys.stdout.flush()
    os._exit(0)


  ###########################
 #                           #
#  #    #    ##    #  #    #  #
#  ##  ##   #  #   #  ##   #  #
#  # ## #  #    #  #  # #  #  #
#  #    #  ######  #  #  # #  #
#  #    #  #    #  #  #   ##  #
#  #    #  #    #  #  #    #  #
 #                           #
  ###########################


options()

signal.signal(signal.SIGINT, handler)
signal.signal(signal.SIGUSR1, handler)
signal.signal(signal.SIGALRM, handler)

try:
    jeedom_utils.write_pid(_pidFile)

    # Configuration du canal pour l'envoi de messages a Jeedom
    jeedom_com = jeedom_com(apikey = _apiKey, url=_callback)
    if (not jeedom_com.test()):
        logging.error('Network communication issue. Unable to send messages to Jeedom')
        shutdown();

    # Réception des message de jeedom qui seont mis en queue dans JEEDOM_SOCKET_MESSAGE
    jeedom_socket = jeedom_socket(port=_socketPort,address=_socketHost)
    jeedom_socket.open()

    signal.alarm(10)

    # Annonce à jeedom que le daemon est démarré
    jeedom_com.send_change_immediate({
        'object' : 'daemon',
        'message': 'started'
    })
 
    # Boucle de traitement des messages mis en queue par jeedom_socket
    try:
        while 1:
            time.sleep(0.5)
            read_socket()
    except KeyboardInterrupt:
        shutdown()

except Exception as e:
    logging.error('Fatal error: ' + str(e))
    logging.info(traceback.format_exc())
    shutdown();

