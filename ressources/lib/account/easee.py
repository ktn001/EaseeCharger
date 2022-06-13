import logging
import json
import os
import sys
import configparser
import time

from signalrcore.hub_connection_builder import HubConnectionBuilder

libDir = os.path.realpath(os.path.dirname(os.path.abspath(__file__)) + '/../')
sys.path.append(libDir)

from jeedom import *
from account import account

class easee(account):

    def getToken(self):
        return self.token

    def start_charger_thread(self,identifiant):
        url = self._url + '/hubs/chargers'
        options = {'access_token_factory': self.getToken}

        if identifiant in self.connections:
            return

        _logLevel = jeedom_utils.get_logLevel()
        _extendedDebug = jeedom_utils.get_extendedDebug()

        if _logLevel == 'error':
            logLevel = logging.ERROR
        elif _logLevel == 'warning':
            logLevel = logging.WARNING
        elif _logLevel == 'info':
            logLevel = logging.INFO
        elif _logLevel == 'debug':
            if _extendedDebug:
                logLevel = logging.DEBUG
            else:
                logLevel = logging.INFO

        connection = HubConnectionBuilder()\
                .with_url(url,options)\
                .configure_logging(logLevel)\
                .with_automatic_reconnect({
                    "type": "raw",
                    "keep_alive_interval": 10,
                    "interval_reconnect": 5,
                    "max_attemps": 5
                }).build()
        self.connections[identifiant] = connection
        connection.on_open(lambda: self.on_open(identifiant))
        connection.on_close(lambda: self.on_close(identifiant))
        connection.on_reconnect(lambda: self.on_reconnect(identifiant))
        connection.on_error(lambda data: self.on_error(data))
        connection.on('ProductUpdate', self.on_Update)
        connection.on('ChargerUpdate', self.on_Update)
        connection.on('CommandResponse', self.on_CommandResponse)
        connection.start()
        return

    def stop_charger_thread(self,serial):
        self.connections[serial].stopping = True
        self.log_debug("------------- " + self.connections[serial].__class__)
        self.connections[serial].stop()
        i = 30
        while serial in self.connections and i > 0:
            time.sleep(1)
            i -= 1
        if i == 0:
            self.log_error(f"Timeout while stopping {serial}")
            return False
        self.log_debug("XXXXXXXXXXXXXX i=" + str(i))
        return True

    def on_open(self,serial):
        self.log_debug("openning connection " + serial)
        self.connections[serial].send("SubscribeWithCurrentState", [serial, True])

    def on_close(self,serial):
        self.log_debug("on_close, serial: " + serial)
        if serial in self.connections:
            if not hasattr(self.connections[serial],'stopping'):
                del self.connections[serial]
                msg2Account  = {}
                msg2Account['cmd'] = 'start_charger_listener'
                msg2Account['identifiant'] =  serial
                self._jeedomQueue.put(json.dumps(msg2Account))
                return
            else:
                msg2Jeedom = {}
                msg2Jeedom['object'] = 'charger'
                msg2Jeedom['modelId'] = 'easee'
                msg2Jeedom['charger'] = serial
                msg2Jeedom['info'] = 'closed'
                self.log_debug("msg2Jeddom: " + str(msg2Jeedom))
                self.send2Jeedom(msg2Jeedom)
                del self.connections[serial]

    def on_reconnect(self,serial):
        self.log_warning("reconnecting to serial " + serial)

    def on_error(self,data):
        self.log_error(data.error)
        self.log_error(data)

    def on_Update(self,messages):
        for message in messages:
            if message == self.lastMessage:
                continue
            self.lastMessage = message
            cmd_id = str(message['id'])
            self.log_debug(f"Traitement de la commande {cmd_id}, value: {message['value']}")
            if not cmd_id in self._mapping['signalR_id']:
                continue
            for logicalId in self._mapping['signalR_id'][cmd_id].split(','):
                msg2Jeedom = {}
                msg2Jeedom['object'] = 'cmd'
                msg2Jeedom['modelId'] = 'easee'
                msg2Jeedom['charger'] = message['mid']
                msg2Jeedom['logicalId'] = logicalId
                msg2Jeedom['value'] = message['value']
                self.log_info("msg2Jeddom: " + str(msg2Jeedom))
                self.send2Jeedom(msg2Jeedom)

    def on_CommandResponse(self,messages):
        pass

    def do_start_account(self,message):
        msg = json.loads(message)
        if not 'token' in msg:
            self.log_error ('do_start(): token is missing')
            return
        if not 'url' in msg:
            self.log_error("do_start(): url is missing")
            return
        self.token = msg['token']
        self._url = msg['url']
        self.lastMessage = None
        return

    def do_stop(self,message):
        if hasattr(self, 'connections'):
            for serial, connection in list(self.connections.items()):
                self.stop_charger_thread(serial)
        return

    def do_newToken(self,message):
        msg = json.loads(message)
        if not hasattr(self,'token') or self.token != msg['token']:
            self.log_debug("Nouveau token reçu")
        else:
            self.log_warning("Reception d'une commande 'newToken' sans modification du token")
            for serial, connection in list(self.connections.items()):
                self.log_info(f"Restarting {serial}...")
                self.stop_charger_thread(serial)
                self.start_charger_listener(serial)
        return

    def do_start_charger_thread(self,message):
        msg = json.loads(message)
        if not 'identifiant' in msg:
            self.log_error("do_start_charger_thread(): identifiant is missing")
            return
        if not hasattr(self,'connections'):
            self.connections = {}
            configDir = os.path.dirname(__file__) + '/../../../core/config/easee'
            self._mapping = configparser.ConfigParser()
            self._mapping.read(f'{configDir}/mapping.ini')
        self.start_charger_thread(msg['identifiant'])

    def do_stop_charger_thread(self,message):
        msg = json.loads(message)
        if not 'identifiant' in msg:
            self.log_error(f"do_stop_charger_thread(): identifiant is missing")
            return
        if not hasattr(self,'connections'):
            return
        if not msg['identifiant'] in self.connections:
            return
        self.stop_charger_thread(msg['identifiant'])

    def test(self, level = 'debug'):
        ok = True
        if not hasattr(self,'_url') or self._url == '':
            self.log_error("l'URL n'est pas définie")
            ok = False
        if ok:
            eval ("self.log_" + level)("RUNNING")
        for serial in self.connections:
            eval ("self.log_" + level)(serial + " is running")
        return ok

