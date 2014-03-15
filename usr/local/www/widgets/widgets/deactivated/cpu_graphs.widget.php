<?php
/*
        $Id$
        Copyright 2007 Scott Dale
        Part of pfSense widgets (https://www.pfsense.org)
        originally based on m0n0wall (http://m0n0.ch/wall)

        Copyright (C) 2004-2005 T. Lechat <dev@lechat.org>, Manuel Kasper <mk@neon1.net>
        and Jonathan Watt <jwatt@jwatt.org>.
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

require_once("guiconfig.inc");
require_once("pfsense-utils.inc");
require_once("functions.inc");
?>
<link  href="/themes/<?=$g['theme'];?>/graphlink.css"  rel="stylesheet"  type="text/css"  />  
<script src="/widgets/javascript/cpu_graphs.js" type="text/javascript"></script>
<script type="text/javascript">
    /* initialize the graph */
    // --- Global Data --- //
    var graphs;        // An array that stores all created graphs
    var graph_dir;     // The direction in which each graph moves
    var last_val;      // An array of values for each graph
    var last_val_span; // References to Last Value span tags for each graph
    var pause;         // Controls execution

    var ajaxStarted = false;

    /**
     * Launches the GraphLink demo. It initializes the graph along with the ajax
     * engine and starts the main execution loop.
     */
    graph         = new Array();
    graph_dir     = new Array();
    last_val      = new Array();
    last_val_span = new Array();
</script>
<div style='display: block; margin-left: auto; margin-right: auto' class="GraphLink" id="GraphOutput"></div>
<script language="javascript" type="text/javascript">

    // Graph 1
    graph[0]         = GraphInitialize('GraphOutput', 200, 50, 4);
    graph_dir[0]     = GL_END;
    last_val[0]      = Math.floor(Math.random() * 50);
    last_val_span[0] = document.getElementById('LastValue0');

    GraphSetVMax(graph[0], 100);
    GraphDynamicScale(graph[0]);

</script>
