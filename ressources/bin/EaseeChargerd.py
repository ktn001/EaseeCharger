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

_logLevel = 'error'
_extendedDebug = False
_callback = ''
_apiKey = ''
_pidFile = '/tmp/jeedom/EaseeCharger/daemon.pid'
_socketPort = -1
_socketHost = 'localhost'
_secureLog = False
accounts = {}

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
# start_account_thread
#...............................................................................
# Lance un thread pour un account
#===============================================================================
def start_account(name, accessToken):
    global accounts
    if name in accounts.keys():
        logging.warning(f"'A thread for account < {name} > is already running")
    logging.info(f"Starting account < {name} >")

#===============================================================================
# process_daemon_message
#...............................................................................
# Traitement de messages de jeedom destinés au daemon
#===============================================================================
def process_daemon_message(message):
    if 'cmd' in message.keys():
        
        # startAccount
        #
        if message['cmd'] == 'startAccount':
            if 'account' not in message.keys():
                logging.error ('Account to start is not defined')
                return
            if 'accessToken' not in message.keys():
                logging.error ('AccessToken is missing')
                return
            start_account(message['account'],message['accessToken'])

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
        payload = json.loads(JEEDOM_SOCKET_MESSAGE.get().decode())
        payload2log = dict(payload)
        if _secureLog:
            if 'accessToken' in payload2log.keys():
                payload2log['accessToken'] = '**********'
        logging.info(f"Message received from Jeedom: {payload2log}")

        if 'object' in payload.keys():
            logging.info ("OBJECT")
            if payload['object'] ==  'daemon':
                process_daemon_message(payload)
            elif payload['object'] == 'account': 
                logging.info ("ACCOUNT")

#===============================================================================
# handler
#...............................................................................
# Procédure appelée pour trapper divers signaux
#===============================================================================
def handler(signum=None, frame=None):
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

