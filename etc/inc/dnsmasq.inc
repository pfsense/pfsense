<?php
/*
	dnsmasq.inc part of pfSense

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
	
	DISABLE_PHP_LINT_CHECKING
	
	pfSense_MODULE:	dnsmasq

*/
require_once('config.inc');

/*
 * Returns an array of configured dnsmasq instances.
 *
 * instanceIndex: (true|false|null|<n>). Index of the instance, true/false for all
 *   instances or null for the default instance. 'true' ignores the 'allow_multi' setting.
 */
function dnsmasq_get_configured_instances($instanceIndex = false) {
	global $config;
	
	// check for old or missing config
	dnsmasq_upgrade_config();
	
	// only return relevant instances
	$a_instances = &$config['dnsmasq']['instances'];
	if (($instanceIndex === false && !isset($config['dnsmasq']['allow_multi']))
			|| ($instanceIndex !== false && $instanceIndex !== true)) {
		$keys = array_keys($a_instances);
		$firstInstance = &$a_instances[$keys[$instanceIndex ?: 0]];
		
		return array($firstInstance);
	}
	return $a_instances;
}

function &dnsmasq_instance_config_by_index($idx) {
	global $config;

	// check for old or missing config
	dnsmasq_upgrade_config();
	
	// only return relevant instance
	$a_instances = &$config['dnsmasq']['instances'];
	$keys = array_keys($a_instances);
	if (!isset($idx) || !isset($keys[$idx])) {
		$idx = 0;
	}
	
	return $a_instances[$keys[$idx]];
}

function dnsmasq_upgrade_config($writeResult = false) {
	global $config;
	
	if (!isset($config['dnsmasq']['instances'])) {
		$config['dnsmasq'] = array(
			'enable' => isset($config['dnsmasq']['enable']),
			'instances' => array('instance0' => $config['dnsmasq']),
			'allow_multi' => isset($config['dnsmasq']['allow_multi']),
		);
		
		if ($writeResult)
			write_config();
	}
}

function dnsmasq_get_instance_file($instanceIndex = null, $appendStar = false, $shortName = false) {
	global $config;

	$basename = "dnsmasq";
	if (($instanceIndex === null || $instanceIndex === false) || !isset($config['dnsmasq']['allow_multi'])) {
		return $basename . ($appendStar ? "*" : "");
	} elseif (is_numericint($instanceIndex)) {
		if ($instanceIndex == 0 && $shortName && isset($config['dnsmasq']['allow_multi'])) {
			$i = 0;
			$instances = dnsmasq_get_configured_instances(true);
			foreach ($instances as $instance) {
				if (isset($instance['enable'])) {
					if (++$i > 1) {
						$shortName = false;
						break;
					}
				}
			}
			if ($shortName) return $basename;
		}
		return $basename. '.' .$instanceIndex;
	}
	return $basename;
}