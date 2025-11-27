export const elementIncludesString = (element, keys, keyword) => {
    let includesString = false;
    keys.forEach((key) => {
        if (element[key].toUpperCase().includes(keyword.toUpperCase())) {
            includesString = true;
        }
    });

    return includesString;
};

export default {
    elementIncludesString,
};
