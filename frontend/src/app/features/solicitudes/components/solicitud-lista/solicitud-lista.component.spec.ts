import { ComponentFixture, TestBed } from '@angular/core/testing';

import { SolicitudListaComponent } from './solicitud-lista.component';

describe('SolicitudListaComponent', () => {
  let component: SolicitudListaComponent;
  let fixture: ComponentFixture<SolicitudListaComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [SolicitudListaComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(SolicitudListaComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
