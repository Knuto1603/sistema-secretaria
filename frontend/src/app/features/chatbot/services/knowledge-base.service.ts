import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';
import { KbArticle, KbDocument, PaginatedData } from '../models/chat.models';

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

@Injectable({ providedIn: 'root' })
export class KnowledgeBaseService {
  private http = inject(HttpClient);
  private api = `${environment.apiUrl}/knowledge-base`;

  // =========================================================================
  // ARTÍCULOS KB
  // =========================================================================

  getArticles(params: Record<string, string | number | boolean> = {}): Observable<PaginatedData<KbArticle>> {
    return this.http
      .get<ApiResponse<PaginatedData<KbArticle>>>(this.api, { params: params as any })
      .pipe(map(r => r.data));
  }

  getArticle(id: string): Observable<KbArticle> {
    return this.http
      .get<ApiResponse<KbArticle>>(`${this.api}/${id}`)
      .pipe(map(r => r.data));
  }

  createArticle(data: Partial<KbArticle>): Observable<KbArticle> {
    return this.http
      .post<ApiResponse<KbArticle>>(this.api, data)
      .pipe(map(r => r.data));
  }

  updateArticle(id: string, data: Partial<KbArticle>): Observable<KbArticle> {
    return this.http
      .put<ApiResponse<KbArticle>>(`${this.api}/${id}`, data)
      .pipe(map(r => r.data));
  }

  deleteArticle(id: string): Observable<void> {
    return this.http
      .delete<ApiResponse<null>>(`${this.api}/${id}`)
      .pipe(map(() => void 0));
  }

  toggleArticle(id: string): Observable<{ activo: boolean }> {
    return this.http
      .patch<ApiResponse<{ activo: boolean }>>(`${this.api}/${id}/toggle`, {})
      .pipe(map(r => r.data));
  }

  addRelation(sourceId: string, targetId: string, tipo = 'relacionado'): Observable<void> {
    return this.http
      .post<ApiResponse<null>>(`${this.api}/${sourceId}/relations`, { target_id: targetId, tipo })
      .pipe(map(() => void 0));
  }

  removeRelation(sourceId: string, targetId: string): Observable<void> {
    return this.http
      .delete<ApiResponse<null>>(`${this.api}/${sourceId}/relations/${targetId}`)
      .pipe(map(() => void 0));
  }

  // =========================================================================
  // DOCUMENTOS
  // =========================================================================

  getDocuments(params: Record<string, string | number | boolean> = {}): Observable<PaginatedData<KbDocument>> {
    return this.http
      .get<ApiResponse<PaginatedData<KbDocument>>>(`${this.api}/documents`, { params: params as any })
      .pipe(map(r => r.data));
  }

  uploadDocument(formData: FormData): Observable<KbDocument> {
    return this.http
      .post<ApiResponse<KbDocument>>(`${this.api}/documents`, formData)
      .pipe(map(r => r.data));
  }

  updateDocument(id: string, data: Partial<KbDocument>): Observable<KbDocument> {
    return this.http
      .put<ApiResponse<KbDocument>>(`${this.api}/documents/${id}`, data)
      .pipe(map(r => r.data));
  }

  deleteDocument(id: string): Observable<void> {
    return this.http
      .delete<ApiResponse<null>>(`${this.api}/documents/${id}`)
      .pipe(map(() => void 0));
  }

  toggleDocument(id: string): Observable<{ activo: boolean }> {
    return this.http
      .patch<ApiResponse<{ activo: boolean }>>(`${this.api}/documents/${id}/toggle`, {})
      .pipe(map(r => r.data));
  }

  reprocessDocument(id: string): Observable<any> {
    return this.http
      .post<ApiResponse<any>>(`${this.api}/documents/${id}/reprocess`, {})
      .pipe(map(r => r.data));
  }

  getDownloadUrl(id: string): string {
    return `${this.api}/documents/${id}/download`;
  }

  // =========================================================================
  // ADJUNTAR / DESADJUNTAR DOCUMENTOS A ARTÍCULOS
  // =========================================================================

  attachDocument(articleId: string, docId: string): Observable<KbArticle> {
    return this.http
      .post<ApiResponse<KbArticle>>(`${this.api}/${articleId}/documents/${docId}`, {})
      .pipe(map(r => r.data));
  }

  detachDocument(articleId: string, docId: string): Observable<void> {
    return this.http
      .delete<ApiResponse<null>>(`${this.api}/${articleId}/documents/${docId}`)
      .pipe(map(() => void 0));
  }
}
