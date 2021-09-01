#!/usr/bin/env python
#
# generate-ffdhe.py
#
# Generate PEM strings for the FFDHE parameters in RFC 7919
# For reference, see https://tools.ietf.org/rfc/rfc7919.txt
#
# This is derived from a similar script from the Mumble project.
# https://github.com/mumble-voip/mumble/blob/master/scripts/generate-ffdhe.py

from __future__ import (unicode_literals, print_function, division)

# Python 3 doesn't have a long type.
# Map the long type to int here, so
# we can easily be compatible with both
# interpreters.
try:
    type(long)
except NameError:
    long = int

import os
import base64
import codecs

# The following P values are directly sourced from RFC 7919 at
# https://tools.ietf.org/rfc/rfc7919.txt

ffdhe_str = {
    '2048': '''
    FFFFFFFF FFFFFFFF ADF85458 A2BB4A9A AFDC5620 273D3CF1
    D8B9C583 CE2D3695 A9E13641 146433FB CC939DCE 249B3EF9
    7D2FE363 630C75D8 F681B202 AEC4617A D3DF1ED5 D5FD6561
    2433F51F 5F066ED0 85636555 3DED1AF3 B557135E 7F57C935
    984F0C70 E0E68B77 E2A689DA F3EFE872 1DF158A1 36ADE735
    30ACCA4F 483A797A BC0AB182 B324FB61 D108A94B B2C8E3FB
    B96ADAB7 60D7F468 1D4F42A3 DE394DF4 AE56EDE7 6372BB19
    0B07A7C8 EE0A6D70 9E02FCE1 CDF7E2EC C03404CD 28342F61
    9172FE9C E98583FF 8E4F1232 EEF28183 C3FE3B1B 4C6FAD73
    3BB5FCBC 2EC22005 C58EF183 7D1683B2 C6F34A26 C1B2EFFA
    886B4238 61285C97 FFFFFFFF FFFFFFFF
    ''',
    '3072': '''
    FFFFFFFF FFFFFFFF ADF85458 A2BB4A9A AFDC5620 273D3CF1
    D8B9C583 CE2D3695 A9E13641 146433FB CC939DCE 249B3EF9
    7D2FE363 630C75D8 F681B202 AEC4617A D3DF1ED5 D5FD6561
    2433F51F 5F066ED0 85636555 3DED1AF3 B557135E 7F57C935
    984F0C70 E0E68B77 E2A689DA F3EFE872 1DF158A1 36ADE735
    30ACCA4F 483A797A BC0AB182 B324FB61 D108A94B B2C8E3FB
    B96ADAB7 60D7F468 1D4F42A3 DE394DF4 AE56EDE7 6372BB19
    0B07A7C8 EE0A6D70 9E02FCE1 CDF7E2EC C03404CD 28342F61
    9172FE9C E98583FF 8E4F1232 EEF28183 C3FE3B1B 4C6FAD73
    3BB5FCBC 2EC22005 C58EF183 7D1683B2 C6F34A26 C1B2EFFA
    886B4238 611FCFDC DE355B3B 6519035B BC34F4DE F99C0238
    61B46FC9 D6E6C907 7AD91D26 91F7F7EE 598CB0FA C186D91C
    AEFE1309 85139270 B4130C93 BC437944 F4FD4452 E2D74DD3
    64F2E21E 71F54BFF 5CAE82AB 9C9DF69E E86D2BC5 22363A0D
    ABC52197 9B0DEADA 1DBF9A42 D5C4484E 0ABCD06B FA53DDEF
    3C1B20EE 3FD59D7C 25E41D2B 66C62E37 FFFFFFFF FFFFFFFF
    ''',
    '4096': '''
    FFFFFFFF FFFFFFFF ADF85458 A2BB4A9A AFDC5620 273D3CF1
    D8B9C583 CE2D3695 A9E13641 146433FB CC939DCE 249B3EF9
    7D2FE363 630C75D8 F681B202 AEC4617A D3DF1ED5 D5FD6561
    2433F51F 5F066ED0 85636555 3DED1AF3 B557135E 7F57C935
    984F0C70 E0E68B77 E2A689DA F3EFE872 1DF158A1 36ADE735
    30ACCA4F 483A797A BC0AB182 B324FB61 D108A94B B2C8E3FB
    B96ADAB7 60D7F468 1D4F42A3 DE394DF4 AE56EDE7 6372BB19
    0B07A7C8 EE0A6D70 9E02FCE1 CDF7E2EC C03404CD 28342F61
    9172FE9C E98583FF 8E4F1232 EEF28183 C3FE3B1B 4C6FAD73
    3BB5FCBC 2EC22005 C58EF183 7D1683B2 C6F34A26 C1B2EFFA
    886B4238 611FCFDC DE355B3B 6519035B BC34F4DE F99C0238
    61B46FC9 D6E6C907 7AD91D26 91F7F7EE 598CB0FA C186D91C
    AEFE1309 85139270 B4130C93 BC437944 F4FD4452 E2D74DD3
    64F2E21E 71F54BFF 5CAE82AB 9C9DF69E E86D2BC5 22363A0D
    ABC52197 9B0DEADA 1DBF9A42 D5C4484E 0ABCD06B FA53DDEF
    3C1B20EE 3FD59D7C 25E41D2B 669E1EF1 6E6F52C3 164DF4FB
    7930E9E4 E58857B6 AC7D5F42 D69F6D18 7763CF1D 55034004
    87F55BA5 7E31CC7A 7135C886 EFB4318A ED6A1E01 2D9E6832
    A907600A 918130C4 6DC778F9 71AD0038 092999A3 33CB8B7A
    1A1DB93D 7140003C 2A4ECEA9 F98D0ACC 0A8291CD CEC97DCF
    8EC9B55A 7F88A46B 4DB5A851 F44182E1 C68A007E 5E655F6A
    FFFFFFFF FFFFFFFF
    ''',
    '6144': '''
    FFFFFFFF FFFFFFFF ADF85458 A2BB4A9A AFDC5620 273D3CF1
    D8B9C583 CE2D3695 A9E13641 146433FB CC939DCE 249B3EF9
    7D2FE363 630C75D8 F681B202 AEC4617A D3DF1ED5 D5FD6561
    2433F51F 5F066ED0 85636555 3DED1AF3 B557135E 7F57C935
    984F0C70 E0E68B77 E2A689DA F3EFE872 1DF158A1 36ADE735
    30ACCA4F 483A797A BC0AB182 B324FB61 D108A94B B2C8E3FB
    B96ADAB7 60D7F468 1D4F42A3 DE394DF4 AE56EDE7 6372BB19
    0B07A7C8 EE0A6D70 9E02FCE1 CDF7E2EC C03404CD 28342F61
    9172FE9C E98583FF 8E4F1232 EEF28183 C3FE3B1B 4C6FAD73
    3BB5FCBC 2EC22005 C58EF183 7D1683B2 C6F34A26 C1B2EFFA
    886B4238 611FCFDC DE355B3B 6519035B BC34F4DE F99C0238
    61B46FC9 D6E6C907 7AD91D26 91F7F7EE 598CB0FA C186D91C
    AEFE1309 85139270 B4130C93 BC437944 F4FD4452 E2D74DD3
    64F2E21E 71F54BFF 5CAE82AB 9C9DF69E E86D2BC5 22363A0D
    ABC52197 9B0DEADA 1DBF9A42 D5C4484E 0ABCD06B FA53DDEF
    3C1B20EE 3FD59D7C 25E41D2B 669E1EF1 6E6F52C3 164DF4FB
    7930E9E4 E58857B6 AC7D5F42 D69F6D18 7763CF1D 55034004
    87F55BA5 7E31CC7A 7135C886 EFB4318A ED6A1E01 2D9E6832
    A907600A 918130C4 6DC778F9 71AD0038 092999A3 33CB8B7A
    1A1DB93D 7140003C 2A4ECEA9 F98D0ACC 0A8291CD CEC97DCF
    8EC9B55A 7F88A46B 4DB5A851 F44182E1 C68A007E 5E0DD902
    0BFD64B6 45036C7A 4E677D2C 38532A3A 23BA4442 CAF53EA6
    3BB45432 9B7624C8 917BDD64 B1C0FD4C B38E8C33 4C701C3A
    CDAD0657 FCCFEC71 9B1F5C3E 4E46041F 388147FB 4CFDB477
    A52471F7 A9A96910 B855322E DB6340D8 A00EF092 350511E3
    0ABEC1FF F9E3A26E 7FB29F8C 183023C3 587E38DA 0077D9B4
    763E4E4B 94B2BBC1 94C6651E 77CAF992 EEAAC023 2A281BF6
    B3A739C1 22611682 0AE8DB58 47A67CBE F9C9091B 462D538C
    D72B0374 6AE77F5E 62292C31 1562A846 505DC82D B854338A
    E49F5235 C95B9117 8CCF2DD5 CACEF403 EC9D1810 C6272B04
    5B3B71F9 DC6B80D6 3FDD4A8E 9ADB1E69 62A69526 D43161C1
    A41D570D 7938DAD4 A40E329C D0E40E65 FFFFFFFF FFFFFFFF
    ''',
    '8192': '''
    FFFFFFFF FFFFFFFF ADF85458 A2BB4A9A AFDC5620 273D3CF1
    D8B9C583 CE2D3695 A9E13641 146433FB CC939DCE 249B3EF9
    7D2FE363 630C75D8 F681B202 AEC4617A D3DF1ED5 D5FD6561
    2433F51F 5F066ED0 85636555 3DED1AF3 B557135E 7F57C935
    984F0C70 E0E68B77 E2A689DA F3EFE872 1DF158A1 36ADE735
    30ACCA4F 483A797A BC0AB182 B324FB61 D108A94B B2C8E3FB
    B96ADAB7 60D7F468 1D4F42A3 DE394DF4 AE56EDE7 6372BB19
    0B07A7C8 EE0A6D70 9E02FCE1 CDF7E2EC C03404CD 28342F61
    9172FE9C E98583FF 8E4F1232 EEF28183 C3FE3B1B 4C6FAD73
    3BB5FCBC 2EC22005 C58EF183 7D1683B2 C6F34A26 C1B2EFFA
    886B4238 611FCFDC DE355B3B 6519035B BC34F4DE F99C0238
    61B46FC9 D6E6C907 7AD91D26 91F7F7EE 598CB0FA C186D91C
    AEFE1309 85139270 B4130C93 BC437944 F4FD4452 E2D74DD3
    64F2E21E 71F54BFF 5CAE82AB 9C9DF69E E86D2BC5 22363A0D
    ABC52197 9B0DEADA 1DBF9A42 D5C4484E 0ABCD06B FA53DDEF
    3C1B20EE 3FD59D7C 25E41D2B 669E1EF1 6E6F52C3 164DF4FB
    7930E9E4 E58857B6 AC7D5F42 D69F6D18 7763CF1D 55034004
    87F55BA5 7E31CC7A 7135C886 EFB4318A ED6A1E01 2D9E6832
    A907600A 918130C4 6DC778F9 71AD0038 092999A3 33CB8B7A
    1A1DB93D 7140003C 2A4ECEA9 F98D0ACC 0A8291CD CEC97DCF
    8EC9B55A 7F88A46B 4DB5A851 F44182E1 C68A007E 5E0DD902
    0BFD64B6 45036C7A 4E677D2C 38532A3A 23BA4442 CAF53EA6
    3BB45432 9B7624C8 917BDD64 B1C0FD4C B38E8C33 4C701C3A
    CDAD0657 FCCFEC71 9B1F5C3E 4E46041F 388147FB 4CFDB477
    A52471F7 A9A96910 B855322E DB6340D8 A00EF092 350511E3
    0ABEC1FF F9E3A26E 7FB29F8C 183023C3 587E38DA 0077D9B4
    763E4E4B 94B2BBC1 94C6651E 77CAF992 EEAAC023 2A281BF6
    B3A739C1 22611682 0AE8DB58 47A67CBE F9C9091B 462D538C
    D72B0374 6AE77F5E 62292C31 1562A846 505DC82D B854338A
    E49F5235 C95B9117 8CCF2DD5 CACEF403 EC9D1810 C6272B04
    5B3B71F9 DC6B80D6 3FDD4A8E 9ADB1E69 62A69526 D43161C1
    A41D570D 7938DAD4 A40E329C CFF46AAA 36AD004C F600C838
    1E425A31 D951AE64 FDB23FCE C9509D43 687FEB69 EDD1CC5E
    0B8CC3BD F64B10EF 86B63142 A3AB8829 555B2F74 7C932665
    CB2C0F1C C01BD702 29388839 D2AF05E4 54504AC7 8B758282
    2846C0BA 35C35F5C 59160CC0 46FD8251 541FC68C 9C86B022
    BB709987 6A460E74 51A8A931 09703FEE 1C217E6C 3826E52C
    51AA691E 0E423CFC 99E9E316 50C1217B 624816CD AD9A95F9
    D5B80194 88D9C0A0 A1FE3075 A577E231 83F81D4A 3F2FA457
    1EFC8CE0 BA8A4FE8 B6855DFE 72B0A66E DED2FBAB FBE58A30
    FAFABE1C 5D71A87E 2F741EF8 C1FE86FE A6BBFDE5 30677F0D
    97D11D49 F7A8443D 0822E506 A9F4614E 011E2A94 838FF88C
    D68C8BB7 C5C6424C FFFFFFFF FFFFFFFF
    '''
    }

# All groups from RFC 7919 use generator 2.

g = '''02'''

def dehexify(hexString):
    '''
        Convert the "bignum" hexString
        representation to a bytearray
        containing the number.
    '''
    return bytearray.fromhex(' '.join(line.strip() for line in hexString.splitlines()))

def derLength(size):
    '''
        Returns the ASN.1 DER length bytes
        for the given size.

        Short form: For lengths <= 127,
        the length is just a byte containing
        the literal number. (Note that for
        numbers <= 127, the MSB is not
        set.)

        In doctest form, that is:

        >>> derLength(1)
        bytearray(b'\\x01')

        >>> derLength(127)
        bytearray(b'\\x7f')

        Long form: For lengths > 127,
        the encoding is different: The
        first byte has the MSB set (that
        is, by itself, 0x80). The rest of
        the bits in the first byte specify
        the number of bytes that follow.

        So, to encode the length 128, you
        would emit

            0x81 0x80

        The high bit of the first byte is set.
        This means the remaining 7 bits contain
        how many bytes follow it. If we mask the
        MSB away, we end up with 0x01. So, only
        a single byte follows.
        The value of the following byte is 0x80,
        which is 128 decimal.

        In doctest form, that is:

        >>> derLength(128)
        bytearray(b'\\x81\\x80')

        Error cases:

        The size passed to this function must be an integer:

        >>> derLength(0.1)
        Traceback (most recent call last):
        ...
        Exception: bad type

        >>> derLength('5')
        Traceback (most recent call last):
        ...
        Exception: bad type

        Negative numbers are not supported, and throw an exception:

        >>> derLength(-1)
        Traceback (most recent call last):
        ...
        Exception: bad size

        The long form does not support very large numbers. This is
        because the 'additional bytes' counter can only count up to
        127 (2**7-1) bytes.

        So, if we try to encode a length that will use exactly 127
        bytes, it will succeed:

        >>> derLength(int('ff'*127, 16)) # doctest:+ELLIPSIS
        bytearray(b'\\xff...\\xff')

        But if we try to go over that limit, we get an exception:

        >>> derLength(int('ff'*128, 16))
        Traceback (most recent call last):
        ...
        Exception: unencodable
    '''

    # Ensure that the passed-in size is an integer.
    if not isinstance(size, (int, long)):
        raise Exception('bad type')

    if size > 127:
        # Convert 'size' to a hexString-style
        # bignum that we can pass to dehexify.
        #
        # Strip '0x' prefix from the hex string.
        hexString = hex(size)[2:]
        # In Python 2.7, the output of hex()
        # can have an L suffix. Make sure we
        # strip that.
        if hexString.endswith('L'):
            hexString = hexString[:-1]
        # Ensure the final hex string only
        # contains full bytes. If not, prepend
        # a zero byte.
        if len(hexString) % 2 != 0:
            hexString = '0' + hexString

        # If the computed hexString bignum
        # contains more than 127 bytes, we
        # can't encode it using ASN.1 DER
        # encoding. Throw an exception.
        nbytes = len(hexString) // 2
        if nbytes > 127:
            raise Exception('unencodable')

        buf = dehexify(hexString)
        out = bytearray((0x80 | nbytes,))
        out += buf
        return out
    elif size > 0:
        # Short form is simply the number itself,
        # as a byte.
        return bytearray((size,))
    else:
        # We don't support negative sizes.
        raise Exception('bad size')

def derSequence(sequence):
    '''
        Encode an ASN.1 DER sequence

        Takes a sequence of DER data as its input.

        Returns a bytearray of the resulting data.

        The encoding of a DER sequence is simply
        an identifier (0x30), a length (the length
        of the content bytes -- see the derLength
        function), and the content itself (just a
        byte stream of other ASN.1 DER objects)
    '''
    out = bytearray((0x30,)) # SEQUENCE tag
    content = bytearray()
    for entry in sequence:
        content += entry
    out += derLength(len(content))
    out += content
    return out

def derUnsignedInteger(hexString):
    '''
        Encode a hex string to a ASN.1 DER INTEGER.
        This function only handles unsigned integers.

        Returns a bytearray of the resulting data.

        The encoding of an ASN.1 DER INTEGER is an
        identifier (0x02), a length (the length of the
        content bytes -- see the derLength function),
        and the content, which in this case is the
        bytes that make up the integer.

        An INTEGER can be signed, or unsigned. Our
        function here only deals with unsigned integers.

        Signed integers have the MSB/sign bit set.

        Our input data is guaranteed to be unsigned,
        so if we detect that the MSB is set in the
        data, we prepend a zero byte to it. This
        is the way to signal that the value is unsigned
        rather than signed.
    '''
    out = bytearray((0x02,)) # INTEGER tag
    buf = dehexify(hexString)

    # If the sign bit is set, prepend a zero byte.
    # Otherwise, the number will be treated as signed,
    # which we don't want.
    if (buf[0] & 0x80) != 0:
        buf = bytearray((0,)) + buf
    out += derLength(len(buf))
    out += buf
    return out

def pem(der, kind='DH PARAMETERS'):
    '''
        Convert the ASN.1 DER data to PEM form.

        Returns the resulting string (type 'unicode' for Python 2).

        The PEM format consists of a header, the content itself (base64,
        using the standard alphabet) and a trailer.

        The header is of the form:
            -----BEGIN $KIND-----

        and the trailer is of the form:
            -----END $KIND-----

        where $KIND defines the type of data
        contained in the PEM data.

        Between the header and the trailer is
        the content itself. The content is the
        DER form encoded using base64.

        Our implementation splits the base64
        data such that each line of base64 data
        can be no longer than 64 characters long.
    '''
    out = u'-----BEGIN {0}-----\n'.format(kind)
    enc = base64.b64encode(der)
    n = 0
    for ch in enc:
        if type(ch) == int: # Python 3
            out += chr(ch)
        else:
            out += ch
        n += 1
        if n == 64:
            out += '\n'
            n = 0
    if out[-1] != '\n':
        out += '\n'
    out += u'-----END {0}-----\n'.format(kind)
    return out

def writePEM(f, identifier, str):
    f.write(str)
            
def main():
    for len, str in ffdhe_str.items():
        f = codecs.open(os.path.join('output', 'dh-parameters.' + len), 'w', 'utf-8')

        writePEM(f, 'ffdhe' + len + '_pem', pem(
            derSequence((
                derUnsignedInteger(str),
                derUnsignedInteger(g),
            ))
        ))

if __name__ == '__main__':
    main()
