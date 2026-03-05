import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, map } from 'rxjs';
import { environment } from '@env/environment';
import {
  ChatConversation,
  ChatConversationDetail,
  ChatSendResponse,
  ChatAnalyticsSummary,
  KnowledgeGap,
  PaginatedData,
  TopTopic,
} from '../models/chat.models';

interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

@Injectable({ providedIn: 'root' })
export class ChatService {
  private http = inject(HttpClient);
  private api = `${environment.apiUrl}/chatbot`;

  getConversations(): Observable<ChatConversation[]> {
    return this.http
      .get<ApiResponse<ChatConversation[]>>(`${this.api}/conversations`)
      .pipe(map(r => r.data));
  }

  newConversation(): Observable<ChatConversation> {
    return this.http
      .post<ApiResponse<ChatConversation>>(`${this.api}/conversations`, {})
      .pipe(map(r => r.data));
  }

  getConversation(id: string): Observable<ChatConversationDetail> {
    return this.http
      .get<ApiResponse<ChatConversationDetail>>(`${this.api}/conversations/${id}`)
      .pipe(map(r => r.data));
  }

  sendMessage(conversationId: string, pregunta: string): Observable<ChatSendResponse> {
    return this.http
      .post<ApiResponse<ChatSendResponse>>(`${this.api}/conversations/${conversationId}/messages`, { pregunta })
      .pipe(map(r => r.data));
  }

  deleteConversation(id: string): Observable<void> {
    return this.http
      .delete<ApiResponse<null>>(`${this.api}/conversations/${id}`)
      .pipe(map(() => void 0));
  }

  // Analytics
  getSummary(days = 30): Observable<ChatAnalyticsSummary> {
    return this.http
      .get<ApiResponse<ChatAnalyticsSummary>>(`${this.api}/analytics/summary`, { params: { days } })
      .pipe(map(r => r.data));
  }

  getTopTopics(params: Record<string, number> = {}): Observable<TopTopic[]> {
    return this.http
      .get<ApiResponse<TopTopic[]>>(`${this.api}/analytics/top-topics`, { params: params as any })
      .pipe(map(r => r.data));
  }

  getKnowledgeGaps(params: Record<string, number> = {}): Observable<PaginatedData<KnowledgeGap>> {
    return this.http
      .get<ApiResponse<PaginatedData<KnowledgeGap>>>(`${this.api}/analytics/knowledge-gaps`, { params: params as any })
      .pipe(map(r => r.data));
  }
}
