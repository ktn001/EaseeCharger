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

class charger():

    def __init__(self,serial):
        self._serial = serial
        
    def log_debug(self,txt):
        logging.debug(f'[charger][{self._serial}]   {txt}')

    def log_info(self,txt):
        logging.info(f'[charger][{self._serial}]   {txt}')

    def log_warning(self,txt):
        logging.warning(f'[charger][{self._serial}]   {txt}')

    def log_error(self,txt):
        logging.error(f'[charger][{self._serial}]   {txt}')
