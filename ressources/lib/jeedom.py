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
#

import time
import logging
from threading import Thread
import asyncio
import requests
from collections.abc import Mapping
import os
from queue import Queue
import socketserver
from socketserver import TCPServer, StreamRequestHandler
import unicodedata
import traceback
import datetime
from os.path import join
import socket
import signal

logger = logging.getLogger("EaseeChargerd")

# ------------------------------------------------------------------------------


class jeedom_com:
    def __init__(self, apikey="", url="", cycle=0.5, retry=3):
        self._apikey = apikey
        self._url = url
        self._cycle = cycle
        self._retry = retry
        self._changes = {}
        if self._cycle > 0:
            Thread(target=self.__changes_async, daemon=True).start()
        logger.debug("Init request module v%s" % (str(requests.__version__),))

    def __changes_async(self):
        if self._cycle <= 0:
            return
        logger.info('Start changes async thread')
        while True:
            try:
                time.sleep(self._cycle)
                if len(self._changes) == 0:
                    continue
                changes = self._changes
                self._changes = {}
                self.__post_change(changes)
            except Exception as error:
                logger.error('Critical error on send_changes_async %s', error)

    def add_changes(self, key, value):
        if key.find("::") != -1:
            tmp_changes = {}
            changes = value
            for k in reversed(key.split("::")):
                if k not in tmp_changes:
                    tmp_changes[k] = {}
                tmp_changes[k] = changes
                changes = tmp_changes
                tmp_changes = {}
            if self._cycle <= 0:
                self.send_change_immediate(changes)
            else:
                self.merge_dict(self._changes, changes)
        else:
            if self._cycle <= 0:
                self.send_change_immediate({key: value})
            else:
                self._changes[key] = value

    def send_change_immediate(self, change):
        Thread(target=self.__post_change, args=(change,)).start()

    def __post_change(self, change):
        logger.debug('Send to jeedom: %s', change)
        for i in range(self._retry):
            try:
                r = requests.post(self._url + '?apikey=' + self._apikey, json=change, timeout=(0.5, 120), verify=False)
                if r.status_code == requests.codes.ok:
                    return True
                else:
                    logger.warning('Error on send request to jeedom, return code %s', r.status_code)
            except Exception as error:
                logger.error('Error on send request to jeedom "%s" retry: %i/%i', error, i, self._retry)
            time.sleep(0.5)
        return False

    def set_change(self, changes):
        self.changes = changes

    def get_change(self):
        return self.changes

    def merge_dict(self, d1, d2):
        for k, v2 in d2.items():
            v1 = d1.get(k)  # returns None if v1 has no value for this key
            if isinstance(v1, Mapping) and isinstance(v2, Mapping):
                self.merge_dict(v1, v2)
            else:
                d1[k] = v2

    def test(self):
        try:
            response = requests.get(self._url + "?apikey=" + self._apikey, verify=False)
            if response.status_code != requests.codes.ok:
                logger.error('Callback error: %s %s. Please check your network configuration page', response.status_code, response.reason)
                return False
        except Exception as e:
            logger.error('Callback result as a unknown error: %s. Please check your network configuration page', e)
            return False
        return True

# ------------------------------------------------------------------------------


class jeedom_utils:

    _logLevel = "error"

    @staticmethod
    def convert_log_level(level="error"):
        LEVELS = {
            "debug": logging.DEBUG,
            "info": logging.INFO,
            "notice": logging.WARNING,
            "warning": logging.WARNING,
            "error": logging.ERROR,
            "critical": logging.CRITICAL,
            "none": logging.CRITICAL,
        }
        return LEVELS.get(level, logging.CRITICAL)

    @staticmethod
    def set_logLevel(level="error"):
        jeedom_utils._logLevel = level
        FORMAT = "[%(asctime)-15s][%(levelname)s] : %(name)-23s %(message)s"
        logging.basicConfig(
            level=jeedom_utils.convert_log_level(level),
            format=FORMAT,
            datefmt="%Y-%m-%d %H:%M:%S",
        )

    @staticmethod
    def get_logLevel():
        return jeedom_utils._logLevel

    @staticmethod
    def stripped(str):
        return "".join([i for i in str if i in range(32, 127)])

    @staticmethod
    def ByteToHex(byteStr):
        return byteStr.hex()

    @staticmethod
    def dec2bin(x, width=8):
        return "".join(str((x >> i) & 1) for i in xrange(width - 1, -1, -1))

    @staticmethod
    def dec2hex(dec):
        if dec is None:
            return "0x00"
        return "0x{:02X}".format(dec)

    @staticmethod
    def testBit(int_type, offset):
        mask = 1 << offset
        return int_type & mask

    @staticmethod
    def clearBit(int_type, offset):
        mask = ~(1 << offset)
        return int_type & mask

    @staticmethod
    def split_len(seq, length):
        return [seq[i : i + length] for i in range(0, len(seq), length)]

    @staticmethod
    def write_pid(path):
        pid = str(os.getpid())
        logger.debug("Writing PID " + pid + " to " + str(path))
        fh = open(path, "w")
        fh.write("%s\n" % pid)
        fh.close()

    @staticmethod
    def remove_accents(input_str):
        nkfd_form = unicodedata.normalize("NFKD", unicode(input_str))
        return "".join([c for c in nkfd_form if not unicodedata.combining(c)])

    @staticmethod
    def printHex(hex):
        return " ".join([hex[i : i + 2] for i in range(0, len(hex), 2)])


# ------------------------------------------------------------------------------

JEEDOM_SOCKET_MESSAGE = asyncio.Queue()

class jeedom_socket:

    def __init__(self, address="localhost", port=-1):
        self.address = address
        self.port = port
        self._server = None

    async def open(self):
        try:
            self._server = await asyncio.start_server(self.handler, self.address, self.port)
            logger.debug(f"Client listen [{self.address}:{self.port}]")
        except OSError as e:
            if e.errno == errno.EADDRINUSE:
                logger.error(f"Le port {self.port} est déjà utilisé")
            else:
                logger.error(f"[jeedom_socket] Erreur system lors de l'ouverture: {e}")
            raise e

        try:
            async with self._server:
                await self._server.serve_forever()
        except asyncio.CancelledError:
            logger.error("[jeedom_socket] Boucle d'écoute interrompue.")


    async def handler(self, reader, writer):
        adresse_client = writer.get_extra_info("peername")
        logger.debug(f"[jeedom_socket] Connexion établie depuis {adresse_client}")

        try:
            while True:
                message = await reader.readline()
                if not message:
                    break
                await JEEDOM_SOCKET_MESSAGE.put(message)
        except asyncio.CancelledError:
            pass
        except Exception as e:
            logger.warning(f"[jeedom_socket] Erreur avec le client {adresse_client} : {e}")
        finally:
            writer.close()
            try:
                await writer.wait_closed()
            except Exception:
                pass


    async def close(self):
        if self._server:
            logger.debug("[jeedom_socket] Arrêt en cours...")
            self._serveur.close()
            await self._server.wait_closed()
            logger.info("[jeedom_socket] Serveur éteint.")




# ------------------------------------------------------------------------------
# END
# ------------------------------------------------------------------------------
