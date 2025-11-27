export const formArrayQueryParam = 
  (name, values) => values.map(v => `&${name}[]=${v}`).join('');

export const getObjParamsFromStr = paramsStr => paramsStr.split('&').reduce((acc, item) => {
  const paramArr = item.split('=');
  acc[paramArr[0]] = paramArr[1];
  return acc;
}, {});

export const getStrParamsFromObj = paramsObj => Object.keys(paramsObj).map(key => `${key}=${paramsObj[key]}`).join('&');
