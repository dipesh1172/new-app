export function requestIs(urlCheck) {
    if (!urlCheck) {
        return false;
    } 
    if (urlCheck === window.location.pathname) {
        return true;
    } 
    let stringPatt = urlCheck.replace(/\//g, '\\/').replace(/\*/g, '.*');
    if (stringPatt.slice(-1) !== '*') {
        stringPatt += '$';
    }
    const patt = new RegExp(stringPatt, 'g');   
    return patt.test(window.location.pathname);
}