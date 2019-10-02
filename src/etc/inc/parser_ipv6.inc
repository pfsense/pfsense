<?php
/*
 * parser_ipv6.inc
 *
 * Copyright (c) 2017-2019 Anders Lind (anders.lind@gmail.com)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/*
 * List of methods used:
 * (?x)  Free-spacing mode.
 *       Ability to use comments, place white space characters without impact,
 *       both newlines and white spaces are simply ignored unless escaped
 *       intentionally.
 *       Use "\ " to indicate a space e.g. like: Hey\ there.
 *       You might want to take a look:
 *       https://www.regular-expressions.info/freespacing.html
 *
 * #  Comment under Free-spacing mode.
 *    If free-spacing mode is not on one can use (?#Some comment)
 *
 * (?(DEFINE)  Subpattern.
 *             Defines a subpattern that we intend to use
 *
 * (?'hexncolon'  A named group.
 *                Can be used to define the group name of a subpattern
 *                or simply to give a matching group a name that is more 'logic'
 *                to use than a numbered group that might even change if the
 *                regular expression is changed.
 *
 * (?&hextet)  Reference to use.
 *             Use/reference to the named group, which might be a subpattern.
 *
 * (?=  Positive lookahead.
 *      Used when we want to make sure something is in the horizon before
 *      we start to match!
 *
 * (?!  Negative lookahead.
 *      Used when we want to make sure something is NOT in the horizon before
 *      we start to match!
 *
 * ()  Capturing group.
 *     Normally identified by a number that corresponds to when it shows up
 *     in the regular expression.
 *
 * (?:  Non-capturing group.
 *      Identifies a Non-capturing group that is useful if you e.g. need to
 *      repeat a match e.g. of a compound expression ab\d, but without capturing
 *      it: (?:ab\d)
 *
 * (?>  Atomic (capturing) group.
 *      When it has a match it throws away all backtracking info it might have
 *      meaning it won't try alternations if there e.g. is a |.
 *
 * \G  We use \G once to alternate away from acceptable characters and instead
 *     match from the point where the last match ended. In our case below it is
 *     used to match at the start of the first line so we do not miss a match.
 *
 * For now everything runs stable.
 *
 * It we want we could make the following changes / investigations in the future:
 *	* At 1. in expression (?>(?&nohexncolonndot)+|\G) experiment with:
 *		\G vs |^|\s+ vs |^
 *		, if we want to optimize on speed/results.
 *		Expression handles cases at start and following matches.
 *
 *	* Make 2 versions:
 *		One for IPv6 only and another that resemble what we have today (IPv6+IPv4).
 *		In that way we would have two regexes that can be chosen from.
 *		That would include when to use hexncolonndot (ipv6+ipv4) vs hexncolon (ipv6)
 *
 *	* Experiment to move check_noclosingsinglecolon to the start right inside of
 *		(?'MATCH'
 *		, to see if we receive a speed improvement (that is stable of course).
 *		My hunch is that it wont work stable and likely require more steps in
 *		general also it seem less useful for the +IPv4 cases so experiment maybe as
 *		well right after: (?'IPV6'
 *
 * Main capturing groups:
 *	MATCH=We have a match
 *
 *	Explanation to naming of the main groups below this section:
 *		C=double colon (::)
 *		L=Left
 *		M=Middle
 *		R=Right
 *		U=Unspecified address (:: alone)
 *		FULL=Full address not compressed with C/double colon
 *		6=IPv6
 *		4=IPv4
 *	, gives:
 *
 *	IPV64
 *	2. FULL64
 *	3. CMR64
 *	4. CLU64
 *
 *	IPV6
 *	5. FULL6
 *	6. CMR6
 *	7. CLU6
 */

const ipv6_regex = <<<'IPV6'
(?x)
	# Definitions:
	(?(DEFINE)(?'hex'[\da-f]))
	(?(DEFINE)(?'hexncolon'[\da-f:]))
	(?(DEFINE)(?'hexncolonndot'[\da-f:\.]))
	(?(DEFINE)(?'hextet'(?&hex){1,4}))
	(?(DEFINE)(?'octet'2[0-5]{2}|1[0-9]{2}|[1-9]?[0-9]))
	(?(DEFINE)(?'ipv4'(?>(?&octet)\.){3}(?&octet)))
	(?(DEFINE)(?'unspecifiedaddr'::))
	(?(DEFINE)(?'nohexncolon'[^\da-f:]))
	(?(DEFINE)(?'nohexncolonndot'[^\da-f:\.]))
	(?(DEFINE)(?'check_withatleastonedoublecolon'
		(?=.*(?&unspecifiedaddr))
	))
	(?(DEFINE)(?'check_withmostonedoublecolon'
		(?!(?&hexncolon)+(?&unspecifiedaddr)(?&hexncolon)+(?&unspecifiedaddr))
	))
	# 1.
	(?(DEFINE)(?'check_noclosingsinglecolon'(?!.*\b:(?>(?&nohexncolon)|$))))
	# Start matching:
	(?>(?&nohexncolonndot)+|\G)
	(?&check_withmostonedoublecolon)
	(?'MATCH'
		(?'IPV64'
			(?'FULL64'
				# 2.
				(?>(?&hextet):){6}(?&ipv4)
			)
		|
			(?'CMR64'
				# 3.
				(?&check_withatleastonedoublecolon)(?>(?&hextet)(?>:{1,2})){1,5}(?&ipv4)
			)
		|
			(?'CLU64'
				# 4.
				(?&unspecifiedaddr)(?>(?&hextet):){0,5}(?&ipv4)
			)
		)
		(?!(?&hexncolonndot))
	|
		(?'IPV6'
				(?'FULL6'
					# 5.
					(?>(?&hextet)(?>:)){7}(?&hextet)
				)
			|
				(?'CMR6'
					# 6.
					(?&check_withatleastonedoublecolon)(?&check_noclosingsinglecolon)
					(?&hextet):{1,2}(?>(?&hextet)(?>:{1,2}|\b)){0,6}
				)
			|
				(?'CLU6'
					# 7.
					(?&check_noclosingsinglecolon)
					(?&unspecifiedaddr)(?>(?&hextet)(?>:|\b)){0,7}
				)
		)
		(?!(?&hexncolonndot))
	)
IPV6;

/*
 * Enumerated comments/documentation
 *
 * 1.
 * check_noclosingsinglecolon checks with negative lookahead what we 'anti' match
 * (remember we do not capture with negative lookahead).
 * check_noclosingsinglecolon defines inside (the inner check) the opposite of
 * what we match. Therefore 'anti' match.
 * The inner check does the following. It is a match
 *	1) that contains something after the (unwanted) :
 * 	or
 * 	2) that is empty after the : (meaning the end of 'line'/input).
 * Inner check: Everything (except newlines), followed by word boundary,
 *							colon, one character of everything BUT NOT (digit, a-f, colon)
 * It means that AFTER the check what we MATCH
 * will be the opposite of .*\b:[some character NOT in \da-f:]
 * meaning something that does not have the same features.
 * The reason why whe have the character class in the end is that it refers to
 * our characters that we use as building blocks in an ipv6 address and anything
 * else can be considered as separators between addresses. So if these building
 * block characters indeed show up the colon would not be the end of the address
 * and not be a 'closing colon'.
 * If a second colon shows up right after - well then it is potentially a
 * shortening of an address meaning something else than a single colon.
 * If in fact there is a 'separator' in the inner check then the check/result is
 * accepted, but flipped around because of the negative lookahead! Meaning we
 * do not match the result afterwards!
 *
 * 2.
 * this must be the first of the 3 expressions - if this came e.g. after the next group
 * it would not match in a line with multiple addresses!
 * matches like: 1111:2222:3333:4444:5555:6666:222.111.333.231
 *
 * 3.
 * matches like: beef:beef::beef:beef:123.123.123.255
 *
 * 4.
 * matches like: ::beef:beef:231.132.213.0
 *
 * 5.
 * this must be the first of the 3 expressions - if this came e.g. after the next group
 * it would not match in a line with multiple addresses!
 * matches like: 1111:2222:3333:4444:5555:6666:7777:8888
 *
 * 6.
 * matches like: beef:beef::beef:beef
 *
 * 7.
 * matches like: ::beef:beef
 * if hexncolonndot is not used above when we start our hextet will typically
 * match the last octet in an ipv4 address when an ipv6 address follows it e.g.:
 * ::1234:1234:1234:1234:1234:1234:123.231.213.255 ::11
 * 255 gets matched by us. Therefore it is important to use hexncolonndot at -START-
 *
 */

/*
 * Small wrapper function to match all the lease content.
 */
function parse_all_ipv6_to_array($content) {
	preg_match_all('/'.ipv6_regex.'/i', $content, $matches, PREG_SET_ORDER);
	return $matches;
}
