import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';

export interface Rol {
  id: number;
  name: string;
  guard_name: string;
  permissions: string[];
  created_at: string;
}

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

@Injectable({
  providedIn: 'root'
})
export class RolService {
  private http = inject(HttpClient);
  private apiUrl = `${environment.apiUrl}/roles`;

  getAll(): Observable<Rol[]> {
    return this.http.get<ApiResponse<Rol[]>>(this.apiUrl).pipe(
      map(response => response.data)
    );
  }

  getById(id: number): Observable<Rol> {
    return this.http.get<ApiResponse<Rol>>(`${this.apiUrl}/${id}`).pipe(
      map(response => response.data)
    );
  }
}
