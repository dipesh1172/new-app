// source: https://glaryjoker.com/article/251.html
export const long2ip = (ip) => {
    if (!isFinite(ip)) { return false; }
    return [
        ip >>> 24,
        ip >>> 16 & 0xFF,
        ip >>> 8 & 0xFF,
        ip & 0xFF,
    ].join('.');
};

// source: https://werxltd.com/wp/2010/05/13/javascript-implementation-of-javas-string-hashcode-method/
export const hashCode = (string) => {
    let hash = 0;
    let char;
    if (string.length == 0) { return hash; }
    for (let i = 0; i < string.length; i++) {
        char = string.charCodeAt(i);
        hash = ((hash << 5) - hash) + char;
        hash &= hash;
    }
    return Math.abs(hash);
};

export function hasProperties(obj, properties) {
    const props = properties.split('.');
    if (typeof obj === 'object' && props.length > 0) {
        for (let i = 0; i < props.length; i++) {
            if (obj.hasOwnProperty(props[i]) && obj[props[i]]) {
                obj = obj[props[i]];
            }
            else {
                return false;
            }
        }
        return true;
    }
    console.log('Object or properties validation fails');
    return false;
}