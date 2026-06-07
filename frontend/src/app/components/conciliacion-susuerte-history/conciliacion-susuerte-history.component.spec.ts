import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ConciliacionSusuerteHistoryComponent } from './conciliacion-susuerte-history.component';
import { ConciliacionSusuerteHistoryService } from '../../services/conciliacion-susuerte-history.service';
import { Router } from '@angular/router';
import { of, throwError } from 'rxjs';
import { FormsModule } from '@angular/forms';

describe('ConciliacionSusuerteHistoryComponent', () => {
  let component: ConciliacionSusuerteHistoryComponent;
  let fixture: ComponentFixture<ConciliacionSusuerteHistoryComponent>;
  let mockHistoryService: any;
  let mockRouter: any;

  beforeEach(async () => {
    mockHistoryService = jasmine.createSpyObj('ConciliacionSusuerteHistoryService', ['getHistory', 'startNewConciliation']);
    mockRouter = jasmine.createSpyObj('Router', ['navigate']);

    // Mock service default return values using dynamic fake implementation to support paging tests
    mockHistoryService.getHistory.and.callFake((page: number = 1, perPage: number = 10) => {
      return of({
        data: [
          { id: 1, total_amount: 100, conciliated_at: '2026-06-04T12:00:00Z', matched_count: 5, details: [] }
        ],
        current_page: page,
        last_page: 3,
        total: 10
      });
    });
    mockHistoryService.startNewConciliation.and.returnValue(of({ message: 'Success' }));

    await TestBed.configureTestingModule({
      imports: [ConciliacionSusuerteHistoryComponent, FormsModule],
      providers: [
        { provide: ConciliacionSusuerteHistoryService, useValue: mockHistoryService },
        { provide: Router, useValue: mockRouter }
      ]
    }).compileComponents();

    fixture = TestBed.createComponent(ConciliacionSusuerteHistoryComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create and load history on init', () => {
    expect(component).toBeTruthy();
    expect(mockHistoryService.getHistory).toHaveBeenCalled();
    expect(component.historyData.length).toBe(1);
    expect(component.historyData[0].id).toBe(1);
  });

  it('should navigate to conciliacion-susuerte when startNewConciliation succeeds', () => {
    component.startNewConciliation();
    expect(mockHistoryService.startNewConciliation).toHaveBeenCalled();
    expect(mockRouter.navigate).toHaveBeenCalledWith(['/conciliacion-susuerte']);
  });

  it('should change page and reload history', () => {
    component.lastPage = 3;
    component.changePage(2);
    expect(component.currentPage).toBe(2);
    expect(mockHistoryService.getHistory).toHaveBeenCalledTimes(2); // Initial + page change
  });
});
