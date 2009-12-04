<?php
/* $Id$ */
/* DISABLE_PHP_LINT_CHECKING                                                  */
/* ========================================================================== */
/*
  transmenu.php
  Copyright (C) 2006 Daniel S. Haischt <me@daniel.stefan.haischt.name>
  All rights reserved.
                                                                              */
/* ========================================================================== */
/*
  Originally part of m0n0wall (http://m0n0.ch/wall)
  Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
  All rights reserved.

  Redistribution and use in source and binary forms, with or without
  modification, are permitted provided that the following conditions are met:

  1. Redistributions of source code must retain the above copyright notice,
     this list of conditions and the following disclaimer.

  2. Redistributions in binary form must reproduce the above copyright
     notice, this list of conditions and the following disclaimer in the
     documentation and/or other materials provided with the distribution.

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
                                                                              */
/* ========================================================================== */

function nervecenterTransmenuGetBodyJS() {
  global $rootmenu;

  if (isset($rootmenu)) {
    return $rootmenu->getMenuJScript();
  } else if (empty($rootmenu)) {
    return "alert('No rootmenu object found.');";
  } else {
    return "alert('No JavaScript attached to rootmenu object.');";
  }
}

?>
