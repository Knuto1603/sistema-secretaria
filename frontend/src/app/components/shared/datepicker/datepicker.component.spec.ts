import { ComponentFixture, TestBed } from '@angular/core/testing';

import { AppDatepickerComponent } from './datepicker.component';

describe('DatapickerComponent', () => {
  let component: AppDatepickerComponent;
  let fixture: ComponentFixture<AppDatepickerComponent>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [AppDatepickerComponent]
    })
    .compileComponents();

    fixture = TestBed.createComponent(AppDatepickerComponent);
    component = fixture.componentInstance;
    fixture.detectChanges();
  });

  it('should create', () => {
    expect(component).toBeTruthy();
  });
});
