import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { environment } from '../../environments/environment';

@Injectable({
  providedIn: 'root'
})
export class ConciliacionSusuerteService {
  private apiUrl = `${environment.apiUrl}/conciliacion-susuerte`;

  constructor(private http: HttpClient) { }

  conciliate(xlsxFile: File, pdfFile: File) {
    const formData = new FormData();
    formData.append('xlsx_file', xlsxFile);
    formData.append('pdf_file', pdfFile);

    return this.http.post<any>(this.apiUrl, formData);
  }
}
