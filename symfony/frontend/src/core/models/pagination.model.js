export class Pagination {
  constructor({ page = 1, limit = 10, total = 0, total_pages = 0, numberOfChips = 5, active = true } = {}) {
    this.currentPage = page || 1
    this.pageSize = limit || 10
    this.totalItems = total || 0
    this.numberOfChips = numberOfChips || 5
    this.active = !!active
    this.totalPages = total_pages || Math.ceil(this.totalItems / this.pageSize)
  }

  setCurrentPage(newValue = 1) {
    this.currentPage = newValue
  }

  getNextPage() {
    return Math.min(this.currentPage + 1, this.totalPages)
  }

  getPrevPage() {
    return Math.max(this.currentPage - 1, 1)
  }

  getPayloadData() {
    return { page: this.currentPage, page_size: this.pageSize }
  }
}
