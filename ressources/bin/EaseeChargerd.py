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
import datetime

libDir = os.path.realpath(os.path.dirname(os.path.abspath(__file__)) + '/../lib')
sys.path.append (libDir)

from logfilter import *
from jeedom import *
from account import Account
from charger import Charger
logger = logging.getLogger('EaseeChargerd')
logger.addFilter(logFilter())
_logLevel = 'error'
_extendedDebug = False
_callback = ''
apiKey = ''
_pidFile = '/tmp/jeedom/EaseeCharger/daemon.pid'
_socketPort = -1
_socketHost = 'localhost'
_startTime = datetime.datetime.fromtimestamp(time.time())

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
    global apiKey
    global _pidFile
    global _socketPort

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
            logFilter.set_quietDebug(False)
        else:
            _logLevel = args.loglevel
            _extendedDebug = False
            logFilter.set_quietDebug(True)
    if args.callback:
        _callback = args.callback
    if args.apikey:
        apiKey = args.apikey
        logFilter.add_sensible(apiKey)
    if args.pid:
        _pidFile = args.pid
    if args.socketport:
        _socketPort = int(args.socketport)
    logFilter.set_secure(args.secureLog)

    jeedom_utils.set_logLevel(_logLevel, _extendedDebug)

    logger.info('Start daemon')
    logger.info('Log level: ' + _logLevel)
    if _logLevel == 'debug':
        logger.info('extendedDebug: ' + str(_extendedDebug))
    logger.info('callback: ' + _callback)
    logger.info('Apikey: ' + apiKey)
    logger.info('Socket Port: ' + str(_socketPort))
    logger.info('Socket Host: ' + _socketHost)
    logger.info('PID file: ' + _pidFile)

#===============================================================================
# logStatus
#...............................................................................
# Log l'état interne du daemon
#===============================================================================
def logStatus():
    logger.info ("┌── Daemon state: ──────────────────────────────────────────")
    logger.info ("│ Daemon:")
    logger.info (f"│   - Started at : {_startTime}")
    logger.info ("│ Accounts:")
    for account in Account.all():
        expiresAt = datetime.datetime.fromtimestamp(account.getExpiresAt())
        lifetime = account.getLifetime()
        time2renew = datetime.datetime.fromtimestamp(account.getTime2renew())
        logger.info (f"│ - {account.getName()}")
        logger.info (f"│   - Token expires at {expiresAt}")
        logger.info (f"│   - Lifetime: {lifetime}")
        logger.info (f"│   - Time to renew: {time2renew}")
    logger.info ("│ Chargers:")
    for charger in Charger.all():
        logger.info (f"│ - {charger.getName()}")
        logger.info (f"│   - Serial:          {charger.getSerial()}")
        accountName = charger.getAccount().getName()
        logger.info (f"│   - Account:         {accountName}")
        logger.info (f"│   - State:           {charger.getState()}")
        logger.info (f"│   - Cloud Connected: {charger.is_running()}")
        logger.info (f"│   - Nb Restart:      {charger.getNbRestart()}")
    logger.info ("└──────────────────────────────────────────────────────────")

#===============================================================================
# start_account
#...............................................................................
# Ajout d'un account
#===============================================================================
def start_account(name, accessToken, refreshToken, expiresAt, expiresIn):
    account = Account.byName(name)
    if account:
        logger.warning(f"Account < {name} > is already defined")
        return
    logger.info(f"Starting account < {name} >")
    account = Account(name, accessToken, refreshToken, expiresAt, expiresIn)
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
        logger.warning(f"Charger {id} is already running")
        return
    logger.info(f"Starting charger {name} (id: {id}) ")
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

        if 'cmd' not in message:
            logger.info(f"Message received from Jeedom: {message}")
            logger.warning("'cmd' is not in message")
            return

        if message['cmd'] not in _commands:
            logger.info(f"Message received from Jeedom: {message}")
            logger.warning(f"Unknow command {message['cmd']} in message")
            return

        for arg in _commands[message['cmd']]:
            if arg not in message:
                logger.error(f"Arg {arg} is missing for cmd {message['cmd']}")
                return

        if message['cmd'] == 'startAccount':
            logFilter.add_sensible(message['accessToken'])
            logFilter.add_sensible(message['refreshToken'])
            logger.info(f"Message received from Jeedom: {message}")
            start_account(message['account'],message['accessToken'],message['refreshToken'],message['expiresAt'],message['expiresIn'])
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
        signal.alarm(3600)
        logger.debug("ALARM")
        logStatus()
        for account in Account.all():
            account.refreshToken()
        return
    logger.debug("Signal %i caught, exiting..." % int(signum))
    shutdown()

#===============================================================================
# shutdown
#...............................................................................
# Procédure d'arrêt du daemon
#===============================================================================
def shutdown():
    logger.debug("Shutdown...")
    try:
        jeedom_socket.close()
    except:
        pass
    for charger in list(Charger.all()):
        charger.remove()
    logger.debug("Removing PID file " + _pidFile)
    try:
        os.remove(_pidFile)
    except:
        pass
    logger.debug("Exit 0")
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
    jeedom_com = jeedom_com(apikey = apiKey, url=_callback)
    if (not jeedom_com.test()):
        logger.error('Network communication issue. Unable to send messages to Jeedom')
        shutdown();
    Charger.set_jeedom_com(jeedom_com)

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
    logger.error('Fatal error: ' + str(e))
    logger.info(traceback.format_exc())
    shutdown();

