var LZString = function ()
{
    function o(o, r)
    {
        if (!t[o])
        {
            t[o] = {};
            for (var n = 0; n < o.length; n++) t[o][o.charAt(n)] = n
        }
        return t[o][r]
    }
    var r = String.fromCharCode,
        n = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=",
        e = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+-$",
        t = {},
        i = {
            compressToBase64: function (o)
            {
                if (null == o) return "";
                var r = i._compress(o, 6, function (o)
                {
                    return n.charAt(o)
                });
                switch (r.length % 4)
                {
                    default:
                        case 0:
                        return r;
                    case 1:
                            return r + "===";
                    case 2:
                            return r + "==";
                    case 3:
                            return r + "="
                }
            },
            decompressFromBase64: function (r)
            {
                return null == r ? "" : "" == r ? null : i._decompress(r.length, 32, function (e)
                {
                    return o(n, r.charAt(e))
                })
            },
            compressToUTF16: function (o)
            {
                return null == o ? "" : i._compress(o, 15, function (o)
                {
                    return r(o + 32)
                }) + " "
            },
            decompressFromUTF16: function (o)
            {
                return null == o ? "" : "" == o ? null : i._decompress(o.length, 16384, function (r)
                {
                    return o.charCodeAt(r) - 32
                })
            },
            compressToUint8Array: function (o)
            {
                for (var r = i.compress(o), n = new Uint8Array(2 * r.length), e = 0, t = r.length; t > e; e++)
                {
                    var s = r.charCodeAt(e);
                    n[2 * e] = s >>> 8, n[2 * e + 1] = s % 256
                }
                return n
            },
            decompressFromUint8Array: function (o)
            {
                if (null === o || void 0 === o) return i.decompress(o);
                for (var n = new Array(o.length / 2), e = 0, t = n.length; t > e; e++) n[e] = 256 * o[2 * e] + o[2 * e + 1];
                var s = [];
                return n.forEach(function (o)
                {
                    s.push(r(o))
                }), i.decompress(s.join(""))
            },
            compressToEncodedURIComponent: function (o)
            {
                return null == o ? "" : i._compress(o, 6, function (o)
                {
                    return e.charAt(o)
                })
            },
            decompressFromEncodedURIComponent: function (r)
            {
                return null == r ? "" : "" == r ? null : (r = r.replace(/ /g, "+"), i._decompress(r.length, 32, function (n)
                {
                    return o(e, r.charAt(n))
                }))
            },
            compress: function (o)
            {
                return i._compress(o, 16, function (o)
                {
                    return r(o)
                })
            },
            _compress: function (o, r, n)
            {
                if (null == o) return "";
                var e, t, i, s = {},
                    p = {},
                    u = "",
                    c = "",
                    a = "",
                    l = 2,
                    f = 3,
                    h = 2,
                    d = [],
                    m = 0,
                    v = 0;
                for (i = 0; i < o.length; i += 1)
                    if (u = o.charAt(i), Object.prototype.hasOwnProperty.call(s, u) || (s[u] = f++, p[u] = !0), c = a + u, Object.prototype.hasOwnProperty.call(s, c)) a = c;
                    else
                    {
                        if (Object.prototype.hasOwnProperty.call(p, a))
                        {
                            if (a.charCodeAt(0) < 256)
                            {
                                for (e = 0; h > e; e++) m <<= 1, v == r - 1 ? (v = 0, d.push(n(m)), m = 0) : v++;
                                for (t = a.charCodeAt(0), e = 0; 8 > e; e++) m = m << 1 | 1 & t, v == r - 1 ? (v = 0, d.push(n(m)), m = 0) : v++, t >>= 1
                            }
                            else
                            {
                                for (t = 1, e = 0; h > e; e++) m = m << 1 | t, v == r - 1 ? (v = 0, d.push(n(m)), m = 0) : v++, t = 0;
                                for (t = a.charCodeAt(0), e = 0; 16 > e; e++) m = m << 1 | 1 & t, v == r - 1 ? (v = 0, d.push(n(m)), m = 0) : v++, t >>= 1
                            }
                            l--, 0 == l && (l = Math.pow(2, h), h++), delete p[a]
                        }
                        else
                            for (t = s[a], e = 0; h > e; e++) m = m << 1 | 1 & t, v == r - 1 ? (v = 0, d.push(n(m)), m = 0) : v++, t >>= 1;
                        l--, 0 == l && (l = Math.pow(2, h), h++), s[c] = f++, a = String(u)
                    }
                if ("" !== a)
                {
                    if (Object.prototype.hasOwnProperty.call(p, a))
                    {
                        if (a.charCodeAt(0) < 256)
                        {
                            for (e = 0; h > e; e++) m <<= 1, v == r - 1 ? (v = 0, d.push(n(m)), m = 0) : v++;
                            for (t = a.charCodeAt(0), e = 0; 8 > e; e++) m = m << 1 | 1 & t, v == r - 1 ? (v = 0, d.push(n(m)), m = 0) : v++, t >>= 1
                        }
                        else
                        {
                            for (t = 1, e = 0; h > e; e++) m = m << 1 | t, v == r - 1 ? (v = 0, d.push(n(m)), m = 0) : v++, t = 0;
                            for (t = a.charCodeAt(0), e = 0; 16 > e; e++) m = m << 1 | 1 & t, v == r - 1 ? (v = 0, d.push(n(m)), m = 0) : v++, t >>= 1
                        }
                        l--, 0 == l && (l = Math.pow(2, h), h++), delete p[a]
                    }
                    else
                        for (t = s[a], e = 0; h > e; e++) m = m << 1 | 1 & t, v == r - 1 ? (v = 0, d.push(n(m)), m = 0) : v++, t >>= 1;
                    l--, 0 == l && (l = Math.pow(2, h), h++)
                }
                for (t = 2, e = 0; h > e; e++) m = m << 1 | 1 & t, v == r - 1 ? (v = 0, d.push(n(m)), m = 0) : v++, t >>= 1;
                for (;;)
                {
                    if (m <<= 1, v == r - 1)
                    {
                        d.push(n(m));
                        break
                    }
                    v++
                }
                return d.join("")
            },
            decompress: function (o)
            {
                return null == o ? "" : "" == o ? null : i._decompress(o.length, 32768, function (r)
                {
                    return o.charCodeAt(r)
                })
            },
            _decompress: function (o, n, e)
            {
                var t, i, s, p, u, c, a, l, f = [],
                    h = 4,
                    d = 4,
                    m = 3,
                    v = "",
                    w = [],
                    A = {
                        val: e(0),
                        position: n,
                        index: 1
                    };
                for (i = 0; 3 > i; i += 1) f[i] = i;
                for (p = 0, c = Math.pow(2, 2), a = 1; a != c;) u = A.val & A.position, A.position >>= 1, 0 == A.position && (A.position = n, A.val = e(A.index++)), p |= (u > 0 ? 1 : 0) * a, a <<= 1;
                switch (t = p)
                {
                    case 0:
                        for (p = 0, c = Math.pow(2, 8), a = 1; a != c;) u = A.val & A.position, A.position >>= 1, 0 == A.position && (A.position = n, A.val = e(A.index++)), p |= (u > 0 ? 1 : 0) * a, a <<= 1;
                        l = r(p);
                        break;
                    case 1:
                        for (p = 0, c = Math.pow(2, 16), a = 1; a != c;) u = A.val & A.position, A.position >>= 1, 0 == A.position && (A.position = n, A.val = e(A.index++)), p |= (u > 0 ? 1 : 0) * a, a <<= 1;
                        l = r(p);
                        break;
                    case 2:
                        return ""
                }
                for (f[3] = l, s = l, w.push(l);;)
                {
                    if (A.index > o) return "";
                    for (p = 0, c = Math.pow(2, m), a = 1; a != c;) u = A.val & A.position, A.position >>= 1, 0 == A.position && (A.position = n, A.val = e(A.index++)), p |= (u > 0 ? 1 : 0) * a, a <<= 1;
                    switch (l = p)
                    {
                        case 0:
                            for (p = 0, c = Math.pow(2, 8), a = 1; a != c;) u = A.val & A.position, A.position >>= 1, 0 == A.position && (A.position = n, A.val = e(A.index++)), p |= (u > 0 ? 1 : 0) * a, a <<= 1;
                            f[d++] = r(p), l = d - 1, h--;
                            break;
                        case 1:
                            for (p = 0, c = Math.pow(2, 16), a = 1; a != c;) u = A.val & A.position, A.position >>= 1, 0 == A.position && (A.position = n, A.val = e(A.index++)), p |= (u > 0 ? 1 : 0) * a, a <<= 1;
                            f[d++] = r(p), l = d - 1, h--;
                            break;
                        case 2:
                            return w.join("")
                    }
                    if (0 == h && (h = Math.pow(2, m), m++), f[l]) v = f[l];
                    else
                    {
                        if (l !== d) return null;
                        v = s + s.charAt(0)
                    }
                    w.push(v), f[d++] = s + v.charAt(0), h--, s = v, 0 == h && (h = Math.pow(2, m), m++)
                }
            }
        };
    return i
}();
"function" == typeof define && define.amd ? define(function ()
{
    return LZString
}) : "undefined" != typeof module && null != module && (module.exports = LZString);

tinymce.PluginManager.add('tpv_flashcard', function (editor, url)
{
    var data = {};
    var lastLen = 0;

    function escapeRegExp(str)
    {
        return str.replace(/([.*+?^=!:${}()|\[\]\/\\])/g, "\\$1");
    }

    function replaceAll(str, find, replace)
    {
        return str.replace(new RegExp(escapeRegExp(find), 'g'), replace);
    }

    function openEditor()
    {
        var q = editor.dom.$('div.flashcard');
        if (q.length > 0)
        {
            data = JSON.parse(LZString.decompressFromBase64(q[0].dataset.fcdata));
        }
        editor.windowManager.open(
        {
            title: 'TPV.com Redbook Flashcard',
            body: [
                {
                    type: 'container',
                    layout: 'flex',
                    direction: 'line',
                    align: 'center',
                    spacing: 5,
                    minWidth: 500,
                    items: [
                    {
                        type: 'label',
                        text: 'Responses'
                    },
                    {
                        type: 'textbox',
                        multiline: true,
                        name: 'clearyn',
                        label: 'Responses',
                        value: data.clearyn
                    },
                    {
                        type: 'label',
                        text: 'Yellow Sheets'
                    },
                    {
                        type: 'textbox',
                        multiline: true,
                        name: 'yellow',
                        label: 'Yellow Sheets',
                        value: data.yellow
                    }, ]
                },


                {
                    type: 'container',
                    layout: 'flex',
                    direction: 'line',
                    align: 'center',
                    spacing: 5,
                    minWidth: 500,
                    items: [
                    {
                        type: 'label',
                        text: 'Repeat/Spell SA'
                    },
                    {
                        type: 'textbox',
                        multiline: true,
                        name: 'repeat',
                        label: 'Repeat SA',
                        value: data.repeat
                    },
                    {
                        type: 'label',
                        text: 'Repeat/Spell CX'
                    },
                    {
                        type: 'textbox',
                        multiline: true,
                        name: 'repeatcx',
                        label: 'Repeat CX',
                        value: data.repeatcx
                    }, ]
                },
                {
                    type: 'container',
                    layout: 'flex',
                    direction: 'line',
                    align: 'center',
                    spacing: 5,
                    minWidth: 500,
                    items: [
                    {
                        type: 'label',
                        text: 'Phonetics'
                    },
                    {
                        type: 'textbox',
                        multiline: true,
                        name: 'phonetics',
                        label: 'Phonetics',
                        value: data.phonetics
                    },
                    {
                        type: 'label',
                        text: 'Accounts'
                    },
                    {
                        type: 'textbox',
                        multiline: true,
                        name: 'accounts',
                        label: 'Accounts',
                        value: data.accounts
                    }, ]
                },
                {
                    type: 'container',
                    layout: 'flex',
                    direction: 'line',
                    align: 'center',
                    spacing: 5,
                    minWidth: 500,
                    items: [
                    {
                        type: 'label',
                        text: 'Hold Time SA'
                    },
                    {
                        type: 'textbox',
                        multiline: true,
                        name: 'holdtimea',
                        label: 'Hold Time SA',
                        value: data.holdtimea
                    },
                    {
                        type: 'label',
                        text: 'Hold Time CX'
                    },
                    {
                        type: 'textbox',
                        multiline: true,
                        name: 'holdtimeb',
                        label: 'Hold Time CX',
                        value: data.holdtimeb
                    }, ]
                },
                {
                    type: 'textbox',
                    multiline: true,
                    name: 'misc',
                    label: 'Miscellaneous',
                    value: data.misc
                },
                {
                    type: 'checkbox',
                    name: 'isClear',
                    label: "Is Clear Yes/No",
                    checked: data.isClear
                },
            ],
            onsubmit: function (e)
            {
                data = e.data;

                var content = editor.getContent();
                var toAdd = '<div class="flashcard mceNonEditable"' +
                    (e.data.isClear ? ' data-clear="true"' : '') +
                    '>' +
                    '<div class="modal-dialog" role="document">' +
                    '<div class="modal-content">' +
                    '<div class="modal-header"><button class="close" type="button" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>' +
                    '<h4 class="modal-title">This is a Flashcard (this text will not be displayed)</h4></div>' +
                    '<div class="modal-body"><table>' +
                    '<thead><tr><th colspan="2"></th></tr></thead>' +
                    '<tbody>' +
                    '<tr>' +
                    '<td class="col-sm-6">' +
                    '<div class="float-left">' +
                    '<strong>Responses: </strong>' +
                    '</div> ' +
                    replaceAll(e.data.clearyn, '\n', '<br/>') +
                    '</td>' +
                    '<td class="col-sm-6">' +
                    '<div class="float-left">' +
                    '<strong>Yellow Sheets: </strong>' +
                    '</div> ' +

                    replaceAll(e.data.yellow, '\n', '<br/>') + '</td>' +
                    '</tr>' +
                    '<tr>' +
                    '<td>' +
                    '<div class="float-left">' +
                    '<strong>Repeat/Spelling SA: </strong>' +
                    '</div> ' +

                    replaceAll(e.data.repeat, '\n', '<br/>') + '</td>' +
                    '<td>' +
                    '<div class="float-left">' +
                    '<strong>Repeat/Spelling CX: </strong>' +
                    '</div> ' +

                    replaceAll(e.data.repeatcx, '\n', '<br/>') + '</td>' +
                    '</tr>' +
                    '<tr>' +
                    '<td>' +
                    '<div class="float-left">' +
                    '<strong>Phonetics:</strong>' +

                    '</div> ' +

                    replaceAll(e.data.phonetics, '\n', '<br/>') + '</td>' +
                    '<td>' +
                    '<div class="float-left">' +
                    '<strong>Accounts: </strong>' +

                    '</div> ' +

                    replaceAll(e.data.accounts, '\n', '<br/>') + '</td>' +
                    '</tr>' +
                    '<tr>' +
                    '<td>' +
                    '<div class="float-left">' +
                    '<strong>Hold Time SA: </strong>' +
                    '</div> ' +

                    replaceAll(e.data.holdtimea, '\n', '<br/>') + '</td>' +

                    '<td>' +
                    '<div class="float-left">' +
                    '<strong>Hold Time CX: </strong>' +
                    '</div> ' +
                    replaceAll(e.data.holdtimeb, '\n', '<br/>') + '</td>' +
                    '</tr>' +
                    '<tr>' +
                    '<td colspan="2">' +

                    '<div class="float-left">' +
                    '<strong>Special Notes: </strong>' +

                    '</div> ' +

                    replaceAll(e.data.misc, '\n', '<br/>') + '</td>' +
                    '</tr>' +
                    '</tbody></table><div class="clearfix"></div></div></div></div></div><br/>';
                if (lastLen > 0)
                {
                    content = content.slice(lastLen);
                }
                lastLen = toAdd.length;
                editor.setContent(toAdd + content);
                var q = editor.dom.$('div.flashcard');
                if (q.length > 0)
                {
                    q[0].dataset.fcdata = LZString.compressToBase64(JSON.stringify(e.data));
                }
                while (q.length > 1)
                {
                    q[1].remove();
                    q = editor.dom.$('div.flashcard');
                }
            },


        });
    };

    editor.addButton('tpv_flashcard' + "_remove",
    {
        icon: "remove",
        onclick: function ()
        {
            var b = tinymce.dom.DomQuery,
                c = editor.dom.getParent(editor.selection.getStart(), 'div.flashcard');
            c && editor.undoManager.transact(function ()
            {
                b(c)
                    .replaceWith("")
            })
        },
        stateSelector: 'div.flashcard'
    });
    editor.addButton('tpv_flashcard',
    {
        icon: 'link',
        onclick: openEditor,
        stateSelector: 'div.flashcard'
    });
    editor.on("init", function ()
    {
        editor.addContextToolbar('div.flashcard', 'tpv_flashcard' + " | " + "tpv_flashcard_remove")
    });
    editor.addMenuItem('tpv_flashcard',
    {
        text: 'Flashcard',
        context: 'insert',
        onclick: openEditor

    });
});