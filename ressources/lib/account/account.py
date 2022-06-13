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
#

import sys
from os.path import exists
import threading
import time
from queue import Queue
from jeedom import *
import logging
import json
import configparser

class account():
    """Class de base pour les differents modèles d'account"""

    def __init__(self, id, modelId, queue, jeedom_com):
        self._id = id
        self._modelId = modelId
        self._jeedomQueue = queue
        self._jeedom_com = jeedom_com
        configDir = os.path.dirname(__file__) + '/../../../core/config'
        self._transforms = configparser.ConfigParser()
        self._transforms.read(f"{configDir}/transforms.ini")
        self._transforms.read(f"{configDir}/{modelId}/transforms.ini")

    def log_debug(self,txt):
        logging.debug(f'[account][{self._modelId}][{self._id}] {txt}')

    def log_info(self,txt):
        logging.info(f'[account][{self._modelId}][{self._id}] {txt}')

    def log_warning(self,txt):
        logging.warning(f'[account][{self._modelId}][{self._id}] {txt}')

    def log_error(self,txt):
        logging.error(f'[account][{self._modelId}][{self._id}] {txt}')

    def send2Jeedom(self,msg):
        msgIsCmd = True
        for key in ('object', 'modelId', 'charger', 'logicalId', 'value'):
            if not key in msg:
                msgIsCmd = False
                break
        msgIsCmd = msgIsCmd and (msg['object'] == 'cmd')
        if msgIsCmd:
            msg['value'] = self._transforms.get(msg['logicalId'],msg['value'],fallback=msg['value'])
        self._jeedom_com.send_change_immediate(msg)

    def read_jeedom_queue(self):
        if not self._jeedomQueue.empty():
            message = self._jeedomQueue.get()
            self.log_debug(f'Processing message: {message}')
            msg = json.loads(message)
            if not 'cmd' in msg:
                self.log_error(f'command is missing in message "{message}"')
                return
            commande = 'do_' + msg['cmd']
            self.log_debug("AAAAAAAAAAAAAAAAAAAAAA " + commande)
            if (hasattr(self, commande)):
                function = eval(f"self.{commande}")
                if callable(function):
                    function(message)
                else:
                    self.log_error(f'Function "{function}" inot found!')

    def listen_jeedom(self):
        self._stop = False
        while 1:
            time.sleep(0.5)
            if self._stop:
                self.log_info(f'arrêt du thread.')
                return
            self.read_jeedom_queue()

    def run(self):
        thread = threading.Thread(target=self.listen_jeedom, args=())
        thread.start()
        self.log_info('Thread démarré.')
        return thread

    def do_stop(self,msg):
        self._stop = True
