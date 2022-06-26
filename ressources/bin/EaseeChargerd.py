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


import string
import sys
import os
import time
import traceback
import re
from optparse import OptionParser
import json
import argparse
import importlib
import time

libDir = os.path.realpath(os.path.dirname(os.path.abspath(__file__)) + '/../lib')
sys.path.append (libDir)

from jeedom import *
import account

_logLevel = "error"
_extendedDebug = False
_socketPort = -1
_socketHost = 'localhost'
_pidfile = '/tmp/jeedom/EaseeCharger/daemond.pid'
_apiKey = ''
_callback = ''
accounts = {}

#===============================================================================
# Options
#...............................................................................
# Prise en compte des options de la ligne de commande
#===============================================================================
def options():
    global _logLevel
    global _extendedDebug
    global _callback
    global _apiKey
    global _pidfile
    global _socketPort

    parser = argparse.ArgumentParser( description='EaseeCharger Daemon for Jeedom plugin')
    parser.add_argument("--loglevel", help="Log Level for the daemon", type=str)
    parser.add_argument("--callback", help="Callback", type=str)
    parser.add_argument("--apikey", help="Apikey", type=str)
    parser.add_argument("--pid", help="Pid file", type=str)
    parser.add_argument("--socketport", help="Port pour réception des commandes du plugin", type=int)
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
        _pidfile = args.pid
    if args.socketport:
        _socketPort = int(args.socketport)

    jeedom_utils.set_logLevel(_logLevel, _extendedDebug)

    logging.info('Start demond')
    logging.info('Log level : '+ _logLevel)
    if _logLevel == 'debug':
        logging.info('extendedDebug : ' + str(_extendedDebug))
    logging.debug('Apikey : '+ _apiKey)
    logging.info('Socket port : '+str(_socketPort))
    logging.info('Socket host : '+str(_socketHost))
    logging.info('PID file : '+str(_pidfile))

def start_account(accountModel, accountName):
    global accounts
    logging.info(f'starting account thread: id={accountName} modèle: {accountModel}')

    if accountName in accounts:
        logging.info(f"Thread for account {accountName} is already running!")
        return

    queue = Queue()
    account = eval("account." + accountModel)(accountName, accountModel, queue, jeedom_com)
    accounts[accountName] = {
            'modelId' : accountModel,
            'queue' : queue,
            'account' : account,
            'thread' : account.run()
            }
    logging.debug(f"Thread for account {accountName} started")

    # On informe Jeedon du démarrage
    jeedom_com.send_change_immediate({
        'object' : 'account',
        'info' : 'thread_started',
        'account_name' : accountName
    })

    return

# -------- Lecture du socket (les messages provenant du plugin)-----------------

def read_socket():
    global JEEDOM_SOCKET_MESSAGE
    global accounts

    if not JEEDOM_SOCKET_MESSAGE.empty():
        # jeedom_socket a reçu un message qu'il a mis en queue. On récupère ici
        logging.debug("Message received in queue JEEDOM_SOCKET_MESSAGE")
        payload = json.loads(JEEDOM_SOCKET_MESSAGE.get().decode())

        # Vérification de la clé API
        if not 'apikey' in payload:
            logging.error("apikey missing from socket : " + str(payload))
            return

        if payload['apikey'] != _apiKey:
            logging.error("Invalid apikey from socket : " + str(payload))
            return

        # Le model de l'account qui a envoyé le message
        if not 'modelId' in payload:
            logging.error("Message without accountModel")
            return
        accountModel = payload['modelId']

        # L'id de l'account qui a envoyé le message
        if not 'id' in payload:
            logging.error(f"Message for accountModel ({accountModel}) but with no 'id'")
            return
        accountName = payload['id']

        # Y-a-t-il un message?
        if not 'message' in payload:
            logging.error(f"Message for accountModel ({accountModel}) and id ({accountName}) but with no 'message'")
            return
        message = json.loads(payload['message'])

        # Lancement du thread de l'account si le message le demande
        if 'cmd' in message and message['cmd'] == 'start_account':
            start_account(accountModel, accountName);

        # Envoi du message dans la queue de traitement de l'account
        if accountName in accounts:
            accounts[accountName]['queue'].put(json.dumps(message))
            # Le message sera repris dans le thread de l'account

        # Si la commande était l'arrêt de l'account...
        if 'cmd' in message and message['cmd'] == 'stop':
            # on retire l'account de la liste
            if 'accountName' in accounts:
                del accounts[accountName]

def showThreads(level = 'debug'):
    eval ('logging.' + level)("Threads en cours:")
    for accountName in accounts:
        accounts[accountName]['account'].test(level)

# ----------- procédures d'arrêt -------------------------------------------

def handler(signum=None, frame=None):
    if (signum == signal.SIGUSR1):
        showThreads('info');
        return;
    logging.debug("Signal %i caught, exiting..." % int(signum))
    shutdown()

def shutdown():
    logging.debug("Shutdown...")
    msgStop = json.dumps({'cmd' : 'stop'})
    for accountName in accounts:
        queue = accounts[accountName]['queue']
        queue.put(msgStop)
    for i in range(10):
        for accountName, account in list(accounts.items()):
            if not account['thread'].is_alive():
                del accounts[accountName]
        if len(accounts) == 0:
            break
        time.sleep(1)
    logging.debug("Removing PID file " + str(_pidfile))
    try:
        os.remove(_pidfile)
    except:
        pass
    try:
        jeedom_socket.close()
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
signal.signal(signal.SIGTERM, handler)
signal.signal(signal.SIGUSR1, handler)

try:
    jeedom_utils.write_pid(str(_pidfile))

    # Configuration du canal pour l'envoi de messages au plugin
    jeedom_com = jeedom_com(apikey = _apiKey, url=_callback)
    if (not jeedom_com.test()):
        logging.error('Network communication issue. Please fixe your Jeedom network configuration.')
        shutdown()

    # Réception des messages du plugin qui seront mis en queue dans JEEDOM_SOCKET_MESSAGE
    jeedom_socket = jeedom_socket(port=_socketPort,address=_socketHost)
    jeedom_socket.open()

    # Envoi d'un message au plugin
    jeedom_com.send_change_immediate({
        'object' : 'daemon',
        'info'   : 'started'
    })

    # Boucle de traitement des messages mis en queue par jeedom_socket
    try:
        while 1:
            time.sleep(0.5)
            read_socket()
    except KeyboardInterrupt:
        shutdown()

except Exception as e:
    logging.error('Fatal error : '+str(e))
    logging.info(traceback.format_exc())
    shutdown()
