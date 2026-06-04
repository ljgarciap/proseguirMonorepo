import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class ConciliacionSusuerteHistoryService {
  private baseUrl = environment.apiUrl;

  constructor(private http: HttpClient) { }

  getHistory(page: number = 1, perPage: number = 15): Observable<any> {
    const params = new HttpParams()
      .set('page', page.toString())
      .set('per_page', perPage.toString());

    return this.http.get<any>(`${this.baseUrl}/conciliaciones-susuerte/history`, { params });
  }

  startNewConciliation(): Observable<any> {
    return this.http.post<any>(`${this.baseUrl}/conciliaciones-susuerte/new`, {});
  }
}
