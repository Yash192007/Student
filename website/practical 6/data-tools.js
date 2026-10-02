export async function loadRecords(url) {
  const response = await fetch(url);

  if (!response.ok) {
    throw new Error(`Request failed (${response.status})`);
  }

  const records = await response.json();
  if (!Array.isArray(records)) {
    throw new Error('The data file must contain a JSON array.');
  }

  return records;
}

export function selectRecords(records, { query, filterKey, filterValue, sortKey, direction }) {
  const normalizedQuery = query.trim().toLocaleLowerCase();
  const multiplier = direction === 'descending' ? -1 : 1;

  return records
    .filter(record => {
      const matchesQuery = !normalizedQuery || Object.values(record)
        .some(value => String(value).toLocaleLowerCase().includes(normalizedQuery));
      const matchesFilter = !filterValue || String(record[filterKey]) === filterValue;
      return matchesQuery && matchesFilter;
    })
    .sort((left, right) => {
      const leftValue = left[sortKey];
      const rightValue = right[sortKey];
      let comparison;

      if (typeof leftValue === 'number' && typeof rightValue === 'number') {
        comparison = leftValue - rightValue;
      } else {
        comparison = String(leftValue ?? '').localeCompare(String(rightValue ?? ''), undefined, {
          numeric: true,
          sensitivity: 'base',
        });
      }

      return comparison * multiplier;
    });
}

export function paginate(records, page, pageSize) {
  const pageCount = Math.max(1, Math.ceil(records.length / pageSize));
  const currentPage = Math.min(Math.max(page, 1), pageCount);
  const startIndex = (currentPage - 1) * pageSize;

  return {
    items: records.slice(startIndex, startIndex + pageSize),
    currentPage,
    pageCount,
    startIndex,
  };
}
