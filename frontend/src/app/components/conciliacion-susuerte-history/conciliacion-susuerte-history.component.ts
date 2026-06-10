import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, RouterModule } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { ConciliacionSusuerteHistoryService } from '../../services/conciliacion-susuerte-history.service';
import Swal from 'sweetalert2';

@Component({
  selector: 'app-conciliacion-susuerte-history',
  standalone: true,
  imports: [CommonModule, RouterModule, FormsModule],
  templateUrl: './conciliacion-susuerte-history.component.html',
  styleUrl: './conciliacion-susuerte-history.component.scss'
})
export class ConciliacionSusuerteHistoryComponent implements OnInit {
  historyData: any[] = [];
  currentPage = 1;
  lastPage = 1;
  perPage = 10;
  totalItems = 0;
  isLoading = false;

  // Filters & Search
  searchTerm: string = '';
  dateFilter: string = '';
  sortField: string = 'conciliated_at';
  sortOrder: 'asc' | 'desc' = 'desc';

  // Modal details
  selectedConciliation: any = null;

  constructor(
    private historyService: ConciliacionSusuerteHistoryService,
    private router: Router
  ) {}

  ngOnInit() {
    this.loadHistory();
  }

  loadHistory() {
    this.isLoading = true;
    this.historyService.getHistory(this.currentPage, this.perPage).subscribe({
      next: (res) => {
        this.historyData = res.data || [];
        this.currentPage = res.current_page || 1;
        this.lastPage = res.last_page || 1;
        this.totalItems = res.total || 0;
        this.isLoading = false;
      },
      error: (err) => {
        this.isLoading = false;
        Swal.fire({
          icon: 'error',
          title: 'Error al cargar historial',
          text: err.error?.message || 'Error al obtener la lista de conciliaciones.'
        });
      }
    });
  }

  get filteredHistory() {
    let filtered = [...this.historyData];

    // Client-side search and filtering
    if (this.searchTerm) {
      const term = this.searchTerm.toLowerCase();
      filtered = filtered.filter(item => 
        item.id.toString().includes(term) ||
        (item.total_amount && item.total_amount.toString().includes(term))
      );
    }

    if (this.dateFilter) {
      filtered = filtered.filter(item => {
        if (!item.conciliated_at) return false;
        return item.conciliated_at.startsWith(this.dateFilter);
      });
    }

    // Sort
    filtered.sort((a, b) => {
      let valA = a[this.sortField];
      let valB = b[this.sortField];

      if (valA === undefined || valA === null) valA = '';
      if (valB === undefined || valB === null) valB = '';

      if (typeof valA === 'number' && typeof valB === 'number') {
        return this.sortOrder === 'asc' ? valA - valB : valB - valA;
      }

      valA = valA.toString().toLowerCase();
      valB = valB.toString().toLowerCase();

      if (valA < valB) return this.sortOrder === 'asc' ? -1 : 1;
      if (valA > valB) return this.sortOrder === 'asc' ? 1 : -1;
      return 0;
    });

    return filtered;
  }

  setSort(field: string) {
    if (this.sortField === field) {
      this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
    } else {
      this.sortField = field;
      this.sortOrder = 'desc';
    }
  }

  changePage(page: number) {
    if (page < 1 || page > this.lastPage) return;
    this.currentPage = page;
    this.loadHistory();
  }

  viewDetails(conciliation: any) {
    this.selectedConciliation = conciliation;
  }

  closeDetails() {
    this.selectedConciliation = null;
  }

  startNewConciliation() {
    this.historyService.startNewConciliation().subscribe({
      next: () => {
        this.router.navigate(['/conciliacion-susuerte']);
      },
      error: (err) => {
        // Fallback to navigating even if endpoint errors
        this.router.navigate(['/conciliacion-susuerte']);
      }
    });
  }

  getStatusClass(status: string): string {
    if (status === 'CONCILIADO') return 'matched';
    if (status === 'SOLO EN SUSUERTE') return 'only-susuerte';
    return 'only-bank';
  }
}
