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

function dnsmasq_get_configured_instances($all = false, $instanceIndex = null) {
	global $config;
	
	/* check for old or missing config */
	if (!isset($config['dnsmasq']['instances']))
		$config['dnsmasq']['instances'] = array('instance0' => $config['dnsmasq']);
	
	$a_instances = &$config['dnsmasq']['instances'];
	if (!$all && (!isset($config['dnsmasq']['allow_multi'])) || $instanceIndex !== null) {
		// only the first instance is processed
		//list($firstInstance, ) = $a_instances;
		$keys = array_keys($a_instances);
		$firstInstance = &$a_instances[$keys[$instanceIndex ?: 0]];
		return array($firstInstance);
	}
	
	return $a_instances;
}

function dnsmasq_get_instance_file($instanceIndex = null, $appendStar = false, $shortName = false) {
	global $config;

	$basename = "dnsmasq";
	if ((empty($instanceIndex) && $instanceIndex != 0) || !isset($config['dnsmasq']['allow_multi'])) {
		return $basename . ($star ? "*" : "");
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