    function deg_to_dms(deg, isLat) {
        var letter = "N";
        if (isLat) {
            if (deg < 0) {
                letter = "S";
            }
        } else {
            letter = "E";
            if (deg < 0) {
                letter = "W";
            }
        }
        if (deg < 0) {
            deg = deg * -1;
        }
        var d = Math.floor(deg);
        var minfloat = (deg - d) * 60;
        var m = Math.floor(minfloat);
        var s = Math.round((minfloat - m) * 6000) / 100;
        return ("" + Math.abs(d) + "º " + m + "' " + s + "\" " + letter);
    }
