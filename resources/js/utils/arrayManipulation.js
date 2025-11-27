export const flatten = (list) => list.reduce((a, b) => a.concat(Array.isArray(b) ? flatten(b) : b), []);

export function arrayValuesAreEmpty(arr) {
    let flag = true;
    for (let index = 0; index < arr.length; index++) {
        if (arr[index]) {
            flag = false;
            break;
        }
    }
    return flag;
}

export function arrayValuesAreEqual(arrEval) {
    return arrEval.every((val, i, arr) => val === arr[0]);
}

export function arrayColumn(arr, column) {
    const newarray = [];
    arr.forEach((element) => {
        if (element.hasOwnProperty(column)) {
            newarray.push(element[column]);
        }
    });
    return newarray;
}

export function arraySort(headers, arr, serviceKey, index) {
    // Validating when an entire column is empty
    const column = arrayColumn(arr, serviceKey);

    if (!arrayValuesAreEmpty(column) && !arrayValuesAreEqual(column)) {
        // less than 0 — a comes before b
        // greater than 0  — b comes before a
        // equal to 0  — a and b are left unchanged with respect to each other

        switch (headers[index].type) {
            case 'date':
                arr.sort((x, y) => {
                    const a = Date.parse(x[serviceKey]) ? Date.parse(x[serviceKey]) : 0;
                    const b = Date.parse(y[serviceKey]) ? Date.parse(y[serviceKey]) : 0;
                    return a - b;
                });
                break;
            case 'number':
                arr.sort((x, y) => {
                    const a = Number(x[serviceKey]) ? Number(x[serviceKey]) : 0;
                    const b = Number(y[serviceKey]) ? Number(y[serviceKey]) : 0;
                    return a - b;
                });
                break;
            default:
                arr.sort((x, y) => {
                    const a = x[serviceKey] ? x[serviceKey].toLowerCase() : '';
                    const b = y[serviceKey] ? y[serviceKey].toLowerCase() : '';

                    if (a < b) {
                        return -1;
                    }
                    if (a > b) {
                        return 1;
                    }
                    return 0;
                });
                break;
        }

        if (headers[index].sorted === 'asc') {
            arr = arr.reverse();
        }
    }

    return arr;
}

export function arrayToTree(arr) {
    const nest = (items, id = null, key = 'parent_id') => items.filter((item) => item[key] === id).map((item) => ({...item, children: nest(items, item.id)}));
    return nest(arr);
}
