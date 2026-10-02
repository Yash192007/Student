import { loadRecords, paginate, selectRecords } from './data-tools.js';

const datasets = {
  events: {
    label: 'Events',
    url: new URL('./events.json', import.meta.url),
    filterKey: 'category',
    filterLabel: 'Category',
  },
  students: {
    label: 'Students',
    url: new URL('./students.json', import.meta.url),
    filterKey: 'program',
    filterLabel: 'Program',
  },
  faqs: {
    label: 'FAQs',
    url: new URL('./faqs.json', import.meta.url),
    filterKey: 'category',
    filterLabel: 'Category',
  },
};

const elements = {
  dataset: document.querySelector('#dataset-picker'),
  search: document.querySelector('#record-search'),
  filterLabel: document.querySelector('#filter-label'),
  filter: document.querySelector('#filter-picker'),
  sort: document.querySelector('#sort-picker'),
  direction: document.querySelector('#sort-direction'),
  pageSize: document.querySelector('#page-size'),
  grid: document.querySelector('#record-grid'),
  status: document.querySelector('#data-status'),
  count: document.querySelector('#result-count'),
  pagination: document.querySelector('#pagination'),
  previous: document.querySelector('#previous-page'),
  next: document.querySelector('#next-page'),
  pageStatus: document.querySelector('#page-status'),
  retry: document.querySelector('#retry-load'),
  workspace: document.querySelector('#data-workspace'),
};

let activeRecords = [];
let currentPage = 1;
let loadError = false;

function fillSelect(select, options, selectedValue) {
  select.replaceChildren(...options.map(({ value, label }) => {
    const option = document.createElement('option');
    option.value = value;
    option.textContent = label;
    return option;
  }));
  if (selectedValue && options.some(option => option.value === selectedValue)) {
    select.value = selectedValue;
  }
}

function configureFields(dataset, records) {
  const keys = records.length ? Object.keys(records[0]) : [];
  const sortSelection = elements.sort.value;
  fillSelect(elements.sort, keys.map(key => ({ value: key, label: key[0].toUpperCase() + key.slice(1) })), sortSelection);

  const values = [...new Set(records.map(record => record[dataset.filterKey]).filter(Boolean))]
    .sort((left, right) => String(left).localeCompare(String(right)));
  elements.filterLabel.textContent = dataset.filterLabel;
  fillSelect(elements.filter, [
    { value: '', label: dataset.filterLabel === 'Category' ? 'All categories' : `All ${dataset.filterLabel.toLocaleLowerCase()}s` },
    ...values.map(value => ({ value: String(value), label: String(value) })),
  ], '');
}

function createRecordCard(record) {
  const card = document.createElement('article');
  card.className = 'data-record';

  const heading = document.createElement('h3');
  heading.textContent = String(record.title ?? record.name ?? record.question ?? `Record ${record.id}`);
  card.append(heading);

  const details = document.createElement('dl');
  for (const [key, value] of Object.entries(record)) {
    if (['title', 'name', 'question'].includes(key)) continue;
    const term = document.createElement('dt');
    term.textContent = key;
    const description = document.createElement('dd');
    description.textContent = String(value);
    details.append(term, description);
  }
  card.append(details);
  return card;
}

function render() {
  const dataset = datasets[elements.dataset.value];
  const filteredRecords = selectRecords(activeRecords, {
    query: elements.search.value,
    filterKey: dataset.filterKey,
    filterValue: elements.filter.value,
    sortKey: elements.sort.value,
    direction: elements.direction.value,
  });
  const pageSize = Number(elements.pageSize.value);
  const result = paginate(filteredRecords, currentPage, pageSize);
  currentPage = result.currentPage;

  elements.grid.replaceChildren(...result.items.map(createRecordCard));
  elements.count.textContent = filteredRecords.length
    ? `Showing ${result.startIndex + 1}-${result.startIndex + result.items.length} of ${filteredRecords.length} ${dataset.label.toLocaleLowerCase()}`
    : `Showing 0 of 0 ${dataset.label.toLocaleLowerCase()}`;
  elements.pageStatus.textContent = `Page ${result.currentPage} of ${result.pageCount}`;
  elements.previous.disabled = result.currentPage === 1;
  elements.next.disabled = result.currentPage === result.pageCount;
  elements.pagination.hidden = filteredRecords.length === 0 || loadError;

  if (!loadError) {
    elements.status.textContent = filteredRecords.length ? '' : 'No records match these options.';
  }
}

async function loadDataset() {
  const dataset = datasets[elements.dataset.value];
  currentPage = 1;
  loadError = false;
  elements.workspace.setAttribute('aria-busy', 'true');
  elements.grid.replaceChildren();
  elements.pagination.hidden = true;
  elements.retry.hidden = true;
  elements.status.className = 'data-status';
  elements.status.textContent = `Loading ${dataset.label.toLocaleLowerCase()}...`;
  elements.count.textContent = '';

  try {
    activeRecords = await loadRecords(dataset.url);
    configureFields(dataset, activeRecords);
    elements.workspace.setAttribute('aria-busy', 'false');
    render();
  } catch (error) {
    loadError = true;
    activeRecords = [];
    elements.workspace.setAttribute('aria-busy', 'false');
    elements.status.className = 'data-status error';
    elements.status.textContent = `Could not load ${dataset.label.toLocaleLowerCase()}: ${error.message}`;
    elements.retry.hidden = false;
  }
}

elements.dataset.addEventListener('change', loadDataset);
[elements.search, elements.filter, elements.sort, elements.direction, elements.pageSize].forEach(control => {
  control.addEventListener('input', () => {
    currentPage = 1;
    render();
  });
  control.addEventListener('change', () => {
    currentPage = 1;
    render();
  });
});
elements.previous.addEventListener('click', () => {
  currentPage -= 1;
  render();
});
elements.next.addEventListener('click', () => {
  currentPage += 1;
  render();
});
elements.retry.addEventListener('click', () => {
  elements.retry.hidden = true;
  loadDataset();
});

loadDataset();
