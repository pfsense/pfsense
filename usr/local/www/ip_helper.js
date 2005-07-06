function gen_bits_lan(ipaddr) {
    if (ipaddr.search(/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/) != -1) {
        var adr = ipaddr.split(/\./);
        if (adr[0] > 255 || adr[1] > 255 || adr[2] > 255 || adr[3] > 255)
            return "";
        if (adr[0] == 0 && adr[1] == 0 && adr[2] == 0 && adr[3] == 0)
            return "";

                if (adr[0] <= 127)
                        return "8";
                else if (adr[0] <= 191)
                        return "16";
                else
                        return "24";
    }
    else
        return "";
}

function gen_bits_opt(ipaddr) {
    if (ipaddr.search(/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/) != -1) {
        var adr = ipaddr.split(/\./);
        if (adr[0] > 255 || adr[1] > 255 || adr[2] > 255 || adr[3] > 255)
            return 0;
        if (adr[0] == 0 && adr[1] == 0 && adr[2] == 0 && adr[3] == 0)
            return 0;

                if (adr[0] <= 127)
                        return 23;
                else if (adr[0] <= 191)
                        return 15;
                else
                        return 7;
    }
    else
        return 0;
}